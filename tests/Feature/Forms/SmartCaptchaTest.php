<?php

namespace Tests\Feature\Forms;

use App\Enums\WorkspaceRole;
use App\Forms\Captcha\CaptchaVerdict;
use App\Forms\Captcha\CaptchaVerifier;
use App\Forms\Captcha\FakeCaptchaVerifier;
use App\Forms\Captcha\YandexSmartCaptchaVerifier;
use App\Forms\SubmissionPipeline;
use App\Models\Form;
use App\Models\Page;
use App\Models\Site;
use App\Models\Submission;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class SmartCaptchaTest extends TestCase
{
    use RefreshDatabase, SubmitsPublishedForms;

    private const CLIENT_KEY = 'ysc1_test_client_key';

    private const SERVER_KEY = 'ysc2_test_server_secret';

    private const TOKEN = 'visitor-captcha-token-value';

    private Site $site;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.yandex_smartcaptcha.client_key' => self::CLIENT_KEY,
            'services.yandex_smartcaptcha.server_key' => self::SERVER_KEY,
        ]);
        $this->site = Site::factory()->create(['form_security' => ['captcha_required' => true]]);
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
    }

    public function test_ok_status_passes_and_request_follows_the_provider_contract(): void
    {
        Http::fake([YandexSmartCaptchaVerifier::VALIDATE_URL => Http::response(['status' => 'ok', 'message' => '', 'host' => 'example.com'])]);

        $this->assertSame(CaptchaVerdict::Passed, $this->yandex()->verify(self::TOKEN, '10.0.0.1'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === YandexSmartCaptchaVerifier::VALIDATE_URL
            && $request->isForm()
            && $request->data() === ['secret' => self::SERVER_KEY, 'token' => self::TOKEN, 'ip' => '10.0.0.1']);
    }

    public function test_failed_status_fails_closed_regardless_of_message(): void
    {
        Http::fakeSequence(YandexSmartCaptchaVerifier::VALIDATE_URL)
            ->push(['status' => 'failed', 'message' => ''])
            ->push(['status' => 'failed', 'message' => 'Invalid or expired Token.']);

        $this->assertSame(CaptchaVerdict::Failed, $this->yandex()->verify(self::TOKEN, null));
        $this->assertSame(CaptchaVerdict::Failed, $this->yandex()->verify('forged', null));
    }

    public function test_network_and_http_errors_fail_open_without_logging_secrets(): void
    {
        Log::spy();
        Http::fakeSequence(YandexSmartCaptchaVerifier::VALIDATE_URL)
            ->pushFailedConnection()
            ->pushStatus(503)
            ->push('<html>maintenance</html>');
        $verifier = $this->yandex();

        $this->assertSame(CaptchaVerdict::Unavailable, $verifier->verify(self::TOKEN, '10.0.0.1'));
        $this->assertSame(CaptchaVerdict::Unavailable, $verifier->verify(self::TOKEN, '10.0.0.1'));
        $this->assertSame(CaptchaVerdict::Unavailable, $verifier->verify(self::TOKEN, '10.0.0.1'));

        Log::shouldHaveReceived('warning')->times(3)->withArgs(function (string $message, array $context): bool {
            $logged = $message.json_encode($context);

            return ! str_contains($logged, self::TOKEN) && ! str_contains($logged, self::SERVER_KEY) && ! str_contains($logged, '10.0.0.1');
        });
    }

    public function test_pipeline_requires_a_valid_token_and_accepts_on_provider_outage(): void
    {
        $this->app->instance(CaptchaVerifier::class, new FakeCaptchaVerifier);

        $this->submit()->assertUnprocessable()->assertJsonPath('message', SubmissionPipeline::CAPTCHA_MESSAGE);
        $this->submit(['captcha_token' => ''])->assertUnprocessable();
        $this->submit(['captcha_token' => 'wrong'])->assertUnprocessable()->assertJsonPath('message', SubmissionPipeline::CAPTCHA_MESSAGE);
        $this->assertSame(0, Submission::query()->count());

        $this->submit(['captcha_token' => FakeCaptchaVerifier::PASS_TOKEN], '79990000001')->assertCreated();
        $this->submit(['captcha_token' => FakeCaptchaVerifier::OUTAGE_TOKEN], '79990000002')->assertCreated();
        $this->assertSame(2, Submission::query()->count());
    }

    public function test_pipeline_uses_the_yandex_adapter_and_never_persists_the_token(): void
    {
        Http::fake([YandexSmartCaptchaVerifier::VALIDATE_URL => Http::response(['status' => 'ok', 'message' => ''])]);

        $this->submit(['captcha_token' => self::TOKEN])->assertCreated();

        Http::assertSentCount(1);
        $this->assertStringNotContainsString(self::TOKEN, (string) json_encode(Submission::query()->sole()->getAttributes()));
    }

    public function test_disabled_or_unconfigured_captcha_is_skipped(): void
    {
        Http::fake();
        $this->site->forceFill(['form_security' => ['captcha_required' => false]])->save();

        $this->submit([], '79990000001')->assertCreated();

        $this->site->forceFill(['form_security' => ['captcha_required' => true]])->save();
        config(['services.yandex_smartcaptcha.server_key' => null]);
        $this->app->forgetInstance(CaptchaVerifier::class);

        $this->submit([], '79990000002')->assertCreated();
        Http::assertNothingSent();
    }

    public function test_browser_receives_only_the_client_key(): void
    {
        $owner = User::factory()->create();
        $this->site->workspace->addMember($owner, WorkspaceRole::Owner);
        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($this->site)->save();

        $preview = $this->as($owner)->get(route('sites.preview', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('captcha', ['provider' => 'yandex', 'client_key' => self::CLIENT_KEY]));
        $this->assertStringNotContainsString(self::SERVER_KEY, (string) $preview->getContent());

        $security = $this->as($owner)->get(route('sites.form-security.show', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('captcha', ['configured' => true]));
        $this->assertStringNotContainsString(self::SERVER_KEY, (string) $security->getContent());

        $this->site->forceFill(['form_security' => ['captcha_required' => false]])->save();
        $this->as($owner)->get(route('sites.preview', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('captcha', null));
    }

    public function test_captcha_can_be_enabled_only_when_configured(): void
    {
        $owner = User::factory()->create();
        $this->site->workspace->addMember($owner, WorkspaceRole::Owner);
        $this->site->forceFill(['form_security' => null])->save();
        $payload = ['ip_limit' => 5, 'ip_window_minutes' => 10, 'phone_limit' => 2, 'phone_window_minutes' => 30, 'duplicate_window_minutes' => 15];

        config(['services.yandex_smartcaptcha.client_key' => null]);
        $this->app->forgetInstance(CaptchaVerifier::class);
        $this->as($owner)->put(route('sites.form-security.update', $this->site), [...$payload, 'captcha_required' => true])
            ->assertSessionHasErrors('captcha_required');
        $this->assertNull($this->site->fresh()?->form_security);

        $this->app->instance(CaptchaVerifier::class, new FakeCaptchaVerifier);
        $this->as($owner)->put(route('sites.form-security.update', $this->site), [...$payload, 'captcha_required' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->site->fresh()?->form_security['captcha_required'] ?? null);
    }

    private function yandex(): YandexSmartCaptchaVerifier
    {
        return new YandexSmartCaptchaVerifier($this->app->make(HttpFactory::class), self::CLIENT_KEY, self::SERVER_KEY);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function submit(array $extra = [], string $phone = '79991112233'): TestResponse
    {
        return $this->submitPublished($this->form, [
            'fields' => ['name' => 'Иван', 'phone' => $phone, 'consent' => true],
            ...$extra,
        ], '10.0.0.1');
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->site->workspace->public_id]);
    }
}
