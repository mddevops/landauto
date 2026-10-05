<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Enums\WorkspaceRole;
use App\Forms\SubmissionGuard;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use App\Models\Submission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class AntiSpamTest extends TestCase
{
    use RefreshDatabase, SubmitsPublishedForms;

    private Site $site;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
    }

    public function test_filled_honeypot_is_rejected_without_persisting(): void
    {
        $this->submit($this->form, '79990000001', extra: ['lf_hp' => 'https://spam.example'])
            ->assertUnprocessable()
            ->assertJsonPath('message', SubmissionGuard::REJECTION_MESSAGE);

        $this->submit($this->form, '79990000001', extra: ['lf_hp' => ''])->assertCreated();
        $this->assertSame(1, Submission::query()->count());
    }

    public function test_ip_limit_is_per_site_and_window(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->submit($this->form, "7999000000{$i}")->assertCreated();
        }

        $this->submit($this->form, '79990000006')
            ->assertTooManyRequests()
            ->assertJsonPath('message', SubmissionGuard::REJECTION_MESSAGE);
        $this->submit($this->form, '79990000006', ip: '10.0.0.2')->assertCreated();

        $otherSiteForm = Form::factory()->withLeadFields()->create();
        $this->submit($otherSiteForm, '79990000007')->assertCreated();

        $this->travel(11)->minutes();
        $this->submit($this->form, '79990000008')->assertCreated();
        $this->assertSame(8, Submission::query()->count());
    }

    public function test_phone_limit_counts_across_forms_of_the_same_site(): void
    {
        $formB = Form::factory()->for($this->site)->withLeadFields()->create();
        $formC = Form::factory()->for($this->site)->withLeadFields()->create();

        $this->submit($this->form, '+7 (999) 111-22-33', ip: '10.0.0.1')->assertCreated();
        $this->submit($formB, '7 999 111 22 33', ip: '10.0.0.2')->assertCreated();
        $this->submit($formC, '79991112233', ip: '10.0.0.3')->assertTooManyRequests();

        $this->submit(Form::factory()->withLeadFields()->create(), '79991112233', ip: '10.0.0.4')->assertCreated();

        $this->travel(31)->minutes();
        $this->submit($formC, '79991112233', ip: '10.0.0.5')->assertCreated();
    }

    public function test_duplicate_form_and_phone_is_blocked_within_the_interval(): void
    {
        $this->submit($this->form, '+7 (999) 111-22-33', ip: '10.0.0.1')->assertCreated();
        $this->submit($this->form, '79991112233', ip: '10.0.0.2')->assertTooManyRequests();

        $this->travel(16)->minutes();
        $this->submit($this->form, '79991112233', ip: '10.0.0.3')->assertCreated();
        $this->assertSame(2, Submission::query()->count());
    }

    public function test_phone_checks_are_skipped_for_forms_without_a_phone(): void
    {
        $form = Form::factory()->for($this->site)->create();
        FormField::factory()->for($form)->create(['key' => 'question', 'type' => FormFieldType::Textarea, 'required' => true]);

        for ($i = 0; $i < 3; $i++) {
            $this->submitPublished($form, ['fields' => ['question' => 'Есть ли в наличии?']])->assertCreated();
        }

        $this->assertNull(Submission::query()->latest('id')->value('phone_normalized'));
    }

    public function test_stored_site_policy_overrides_defaults_and_invalid_values_fall_back(): void
    {
        $this->site->form_security = ['ip_limit' => 1, 'duplicate_window_minutes' => 0, 'phone_limit' => 0];
        $this->site->save();

        $this->submit($this->form, '79991112233')->assertCreated();
        $this->submit($this->form, '79991112233', ip: '10.0.0.2')->assertCreated();
        $this->submit($this->form, '79990000000')->assertTooManyRequests();
        $this->submit($this->form, '79991112233', ip: '10.0.0.3')->assertTooManyRequests();
    }

    public function test_security_page_is_editable_only_with_edit_forms(): void
    {
        $workspace = $this->site->workspace;
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $designer = User::factory()->create();
        $workspace->addMember($owner, WorkspaceRole::Owner);
        $workspace->addMember($admin, WorkspaceRole::Admin);
        $workspace->addMember($designer, WorkspaceRole::Designer);
        $payload = ['ip_limit' => 3, 'ip_window_minutes' => 5, 'phone_limit' => 1, 'phone_window_minutes' => 60, 'duplicate_window_minutes' => 0];

        $this->as($designer, $workspace)->get(route('sites.form-security.show', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/form-security')
                ->where('policy.ip_limit', 5)
                ->where('policy.phone_window_minutes', 30)
                ->where('can.edit', false));
        $this->as($designer, $workspace)->put(route('sites.form-security.update', $this->site), $payload)->assertForbidden();

        $this->as($admin, $workspace)->get(route('sites.form-security.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('can.edit', true));
        $this->as($admin, $workspace)->put(route('sites.form-security.update', $this->site), $payload)->assertSessionHasNoErrors();

        $this->as($owner, $workspace)->put(route('sites.form-security.update', $this->site), [...$payload, 'ip_limit' => 0])->assertSessionHasErrors('ip_limit');
        $this->as($owner, $workspace)->put(route('sites.form-security.update', $this->site), $payload)->assertSessionHasNoErrors();
        $this->assertSame($payload, array_intersect_key((array) $this->site->fresh()?->form_security, $payload));

        $this->as($owner, $workspace)->get(route('sites.form-security.show', Site::factory()->create()))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function submit(Form $form, string $phone, string $ip = '10.0.0.1', array $extra = []): TestResponse
    {
        return $this->submitPublished($form, [
            'fields' => ['name' => 'Иван', 'phone' => $phone, 'consent' => true],
            ...$extra,
        ], $ip);
    }

    private function as(User $user, Workspace $workspace): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);
    }
}
