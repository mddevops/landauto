<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Enums\SubmissionStatus;
use App\Enums\WorkspaceRole;
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
use LogicException;
use Tests\TestCase;

class SubmissionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
        FormField::factory()->for($this->form)->create(['key' => 'email', 'type' => FormFieldType::Email, 'label' => 'Почта', 'required' => false, 'sort_order' => 9]);
    }

    public function test_valid_submission_is_persisted_with_a_snapshot_and_no_raw_request(): void
    {
        $this->submit(['fields' => $this->fields()], [
            'User-Agent' => str_repeat('Agent', 100),
            'Authorization' => 'Bearer secret-header-token',
            'Cookie' => 'session=secret-cookie',
        ])->assertCreated();

        $submission = Submission::query()->sole();

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $submission->public_id);
        $this->assertSame([$this->site->id, $this->form->id, SubmissionStatus::Received], [$submission->site_id, $submission->form_id, $submission->status]);
        $this->assertSame([
            ['key' => 'name', 'type' => 'text', 'label' => 'Имя', 'value' => 'Иван'],
            ['key' => 'phone', 'type' => 'phone', 'label' => 'Телефон', 'value' => '+7 (999) 111-22-33'],
            ['key' => 'consent', 'type' => 'consent', 'label' => 'Согласен на обработку данных', 'value' => true],
            ['key' => 'email', 'type' => 'email', 'label' => 'Почта', 'value' => 'Client@Example.RU'],
        ], $submission->payload);
        $this->assertSame('+7 (999) 111-22-33', $submission->phone_original);
        $this->assertSame('client@example.ru', $submission->email_normalized);
        $this->assertSame('127.0.0.1', $submission->ip);
        $this->assertSame(255, mb_strlen((string) $submission->user_agent));
        $this->assertNotNull($submission->submitted_at);

        $row = json_encode(Submission::query()->toBase()->sole(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('secret-header-token', $row);
        $this->assertStringNotContainsString('secret-cookie', $row);
    }

    public function test_invalid_inactive_and_spoofed_requests_are_not_persisted(): void
    {
        $this->submit(['fields' => $this->fields(['phone' => ''])])->assertUnprocessable();
        $this->submit(['fields' => $this->fields(), 'site_id' => Site::factory()->create()->id])->assertUnprocessable();

        $this->form->update(['status' => false]);
        $this->submit(['fields' => $this->fields()])->assertNotFound();

        $this->assertSame(0, Submission::query()->count());
    }

    public function test_submission_belongs_to_the_form_site_and_snapshot_is_immutable(): void
    {
        $submission = Submission::factory()->for($this->form)->create();

        $submission->payload = [];
        $this->assertThrows(fn () => $submission->save(), LogicException::class);

        $foreign = new Submission;
        $foreign->forceFill([
            'site_id' => Site::factory()->create()->id,
            'form_id' => $this->form->id,
            'payload' => [],
            'submitted_at' => now(),
        ]);
        $this->assertThrows(fn () => $foreign->save(), LogicException::class);
    }

    public function test_history_survives_form_edits(): void
    {
        $this->submit(['fields' => $this->fields()])->assertCreated();

        $this->form->fields()->where('key', 'name')->sole()->update(['label' => 'Как к вам обращаться']);
        $this->form->fields()->where('key', 'email')->sole()->delete();
        $this->form->update(['name' => 'Новая форма']);

        $payload = Submission::query()->sole()->payload;
        $this->assertSame('Имя', $payload[0]['label']);
        $this->assertSame('Client@Example.RU', $payload[3]['value']);
    }

    public function test_submissions_list_follows_view_submissions_permission_and_hides_request_metadata(): void
    {
        $this->submit(['fields' => $this->fields()])->assertCreated();
        Submission::factory()->create();
        $members = [];

        foreach ([WorkspaceRole::Owner, WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $members[$role->value] = User::factory()->create();
            $this->workspace->addMember($members[$role->value], $role);
        }

        foreach (['owner', 'admin'] as $role) {
            $this->as($members[$role])->get(route('sites.submissions.index', $this->site))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('sites/submissions/index')
                    ->has('submissions', 1)
                    ->where('submissions.0.form.public_id', $this->form->public_id)
                    ->where('submissions.0.values.1.value', '+7 (999) 111-22-33')
                    ->missing('submissions.0.ip')
                    ->missing('submissions.0.user_agent')
                    ->missing('submissions.0.id'));
        }

        foreach (['designer', 'content_editor'] as $role) {
            $this->as($members[$role])->get(route('sites.submissions.index', $this->site))->assertForbidden();
        }

        $this->as($members['owner'])->get(route('sites.submissions.index', Site::factory()->create()))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function submit(array $payload, array $headers = []): TestResponse
    {
        return $this->postJson(route('forms.submissions.store', $this->form->public_id), $payload, $headers);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fields(array $overrides = []): array
    {
        return ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'consent' => true, 'email' => 'Client@Example.RU', ...$overrides];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
