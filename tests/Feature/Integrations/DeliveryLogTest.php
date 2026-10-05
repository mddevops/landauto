<?php

namespace Tests\Feature\Integrations;

use App\Enums\DeliveryStatus;
use App\Enums\WorkspaceRole;
use App\Integrations\Delivery\DeliveryAdapters;
use App\Integrations\Delivery\DeliveryDispatcher;
use App\Integrations\Delivery\DeliveryResult;
use App\Models\Form;
use App\Models\FormRoute;
use App\Models\IntegrationProfile;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeDeliveryAdapter;
use Tests\TestCase;

class DeliveryLogTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create();
        $form = Form::factory()->for($this->site)->withLeadFields()->create(['name' => 'Тест-драйв']);
        $profile = IntegrationProfile::factory()->create(['workspace_id' => $this->site->workspace_id, 'encrypted_credentials' => ['token' => 'log-page-token-XYZ1']]);
        $binding = SiteIntegrationBinding::factory()->for($this->site)->create(['integration_profile_id' => $profile->id]);
        FormRoute::factory()->for($form)->create(['name' => 'Почта отдела продаж', 'sort_order' => 1]);
        FormRoute::factory()->for($form)->webhook($binding)->create(['name' => 'CRM дилера', 'sort_order' => 2]);
        $this->submission = Submission::factory()->for($form)->create([
            'payload' => [['key' => 'phone', 'type' => 'phone', 'label' => 'Телефон', 'value' => '+7 999 000-11-22']],
        ]);

        $adapter = (new FakeDeliveryAdapter)->queue(
            DeliveryResult::success(200),
            DeliveryResult::permanent('http_401', 'Сервис отклонил авторизацию. Проверьте доступ в профиле интеграции.', 401),
        );
        $this->app->instance(DeliveryAdapters::class, new DeliveryAdapters([$adapter]));
        app(DeliveryDispatcher::class)->dispatchFor($this->submission);
    }

    public function test_admin_sees_statuses_attempts_and_safe_errors_without_lead_values_or_secrets(): void
    {
        $failed = SubmissionDelivery::query()->where('status', DeliveryStatus::Failed->value)->sole();

        $response = $this->as(WorkspaceRole::Admin)->get(route('sites.deliveries.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/deliveries/index')
                ->where('can.retry', true)
                ->has('deliveries', 2)
                ->where('deliveries.0.public_id', $failed->public_id)
                ->where('deliveries.0.route', 'CRM дилера')
                ->where('deliveries.0.status', 'failed')
                ->where('deliveries.0.status_label', 'Ошибка')
                ->where('deliveries.0.http_status', 401)
                ->where('deliveries.0.error', 'Сервис отклонил авторизацию. Проверьте доступ в профиле интеграции.')
                ->where('deliveries.0.attempts.0.number', 1)
                ->where('deliveries.0.attempts.0.succeeded', false)
                ->where('deliveries.1.status', 'delivered')
                ->where('deliveries.1.form', 'Тест-драйв'));

        $html = (string) $response->getContent();
        $this->assertStringNotContainsString('log-page-token-XYZ1', $html);
        $this->assertStringNotContainsString('+7 999 000-11-22', $html);
        $this->assertStringNotContainsString('"id":', $html);

        $this->as(WorkspaceRole::Admin)->get(route('sites.deliveries.index', [$this->site, 'status' => 'delivered']))
            ->assertInertia(fn (Assert $page) => $page->where('status', 'delivered')->has('deliveries', 1));
    }

    public function test_only_members_with_delivery_log_permission_see_the_log(): void
    {
        $url = route('sites.deliveries.index', $this->site);

        $this->as(WorkspaceRole::Owner)->get($url)->assertOk();
        $this->as(WorkspaceRole::Designer)->get($url)->assertForbidden();
        $this->as(WorkspaceRole::ContentEditor)->get($url)->assertForbidden();

        $outsider = User::factory()->create();
        $other = Site::factory()->create();
        $other->workspace->addMember($outsider, WorkspaceRole::Owner);
        $this->actingAs($outsider)->withSession([WorkspaceContext::SESSION_KEY => $other->workspace->public_id])
            ->get($url)->assertNotFound();
    }

    public function test_submissions_page_shows_delivery_status_only_with_log_permission(): void
    {
        $this->as(WorkspaceRole::Admin)->get(route('sites.submissions.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewDeliveries', true)
                ->where('submissions.0.deliveries', [
                    ['route' => 'Почта отдела продаж', 'status' => 'delivered', 'status_label' => 'Доставлено'],
                    ['route' => 'CRM дилера', 'status' => 'failed', 'status_label' => 'Ошибка'],
                ]));

        Page::factory()->for($this->site)->home()->create();
        $this->as(WorkspaceRole::Owner)->get(route('sites.designer', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('can.viewDeliveryLogs', true));

        $this->as(WorkspaceRole::Designer)->get(route('sites.submissions.index', $this->site))->assertForbidden();
    }

    private function as(WorkspaceRole $role): self
    {
        $user = User::factory()->create();
        $this->site->workspace->addMember($user, $role);

        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->site->workspace->public_id]);
    }
}
