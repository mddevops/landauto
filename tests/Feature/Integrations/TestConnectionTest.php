<?php

namespace Tests\Feature\Integrations;

use App\Enums\WorkspaceRole;
use App\Integrations\Http\HostResolver;
use App\Models\IntegrationProfile;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

class TestConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-conn-token-SECRET-9Q';

    private Workspace $workspace;

    private IntegrationProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new FakeHostResolver([
            'crm.example.com' => ['93.184.216.34'],
            'intranet.example.com' => ['192.168.10.10'],
        ]));
        $this->workspace = Workspace::factory()->create();
        $this->profile = IntegrationProfile::factory()->create([
            'workspace_id' => $this->workspace->id,
            'base_url' => 'https://crm.example.com/hook',
            'encrypted_credentials' => ['token' => self::TOKEN],
        ]);
    }

    public function test_successful_check_uses_the_delivery_transport_and_reports_only_safe_facts(): void
    {
        Http::fake(['*' => Http::response('{"ok":true,"echo":"'.self::TOKEN.'"}', 200)]);

        $response = $this->as(WorkspaceRole::Admin)->post(route('integrations.test', $this->profile->public_id))->assertRedirect();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://crm.example.com/hook'
            && $request->header('Authorization') === ['Bearer '.self::TOKEN]
            && str_starts_with($request->header('Idempotency-Key')[0], 'test-')
            && $request->data() === ['event' => 'landflow.test_connection']);

        $response->assertInertiaFlash('toast.type', 'success');
        $message = (string) json_encode(session('inertia.flash_data'));
        $this->assertStringContainsString('HTTP 200', $message);
        $this->assertStringNotContainsString(self::TOKEN, $message);
        $this->assertSame(0, Submission::query()->count());
        $this->assertSame(0, SubmissionDelivery::query()->count());
    }

    public function test_failed_check_shows_a_safe_message_without_the_provider_body(): void
    {
        Http::fake(['*' => Http::response('{"error":"invalid token '.self::TOKEN.'"}', 401)]);

        $this->as(WorkspaceRole::Owner)->post(route('integrations.test', $this->profile->public_id))
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message', 'Проверка не прошла: Сервис отклонил авторизацию. Проверьте доступ в профиле интеграции. (HTTP 401)');
    }

    public function test_private_urls_are_rejected_by_the_same_policy_without_a_request(): void
    {
        Http::fake();

        foreach (['https://intranet.example.com/', 'https://127.0.0.1/', 'https://169.254.169.254/latest/meta-data/'] as $url) {
            $this->profile->forceFill(['base_url' => $url])->save();
            $this->as(WorkspaceRole::Owner)->post(route('integrations.test', $this->profile->public_id))
                ->assertInertiaFlash('toast.message', 'Проверка не прошла: Адрес назначения запрещён политикой безопасности.');
        }

        Http::assertNothingSent();
    }

    public function test_requires_manage_integrations_and_stays_in_the_workspace(): void
    {
        Http::fake();
        $url = route('integrations.test', $this->profile->public_id);

        $this->as(WorkspaceRole::Designer)->post($url)->assertForbidden();
        $this->as(WorkspaceRole::ContentEditor)->post($url)->assertForbidden();

        $outsider = User::factory()->create();
        $other = Workspace::factory()->create();
        $other->addMember($outsider, WorkspaceRole::Owner);
        $this->actingAs($outsider)->withSession([WorkspaceContext::SESSION_KEY => $other->public_id])->post($url)->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_checks_are_rate_limited(): void
    {
        config(['integrations.test_connection_per_minute' => 2]);
        Http::fake(['*' => Http::response('', 204)]);
        $owner = $this->member(WorkspaceRole::Owner);
        $as = fn () => $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);

        $as()->post(route('integrations.test', $this->profile->public_id))->assertInertiaFlash('toast.type', 'success');
        $as()->post(route('integrations.test', $this->profile->public_id))->assertInertiaFlash('toast.type', 'success');
        $as()->post(route('integrations.test', $this->profile->public_id))
            ->assertInertiaFlash('toast.message', 'Слишком много проверок. Попробуйте через минуту.');

        Http::assertSentCount(2);
    }

    private function member(WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $this->workspace->addMember($user, $role);

        return $user;
    }

    private function as(WorkspaceRole $role): self
    {
        return $this->actingAs($this->member($role))->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
