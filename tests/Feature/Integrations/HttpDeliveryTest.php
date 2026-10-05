<?php

namespace Tests\Feature\Integrations;

use App\Enums\DeliveryStatus;
use App\Enums\IntegrationAuthType;
use App\Integrations\Delivery\DeliveryDispatcher;
use App\Integrations\Http\HostResolver;
use App\Models\Form;
use App\Models\FormRoute;
use App\Models\IntegrationProfile;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use App\Models\SubmissionDeliveryAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeHostResolver;
use Tests\TestCase;

class HttpDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'live-token-SECRET-7K2F';

    private IntegrationProfile $profile;

    private FormRoute $route;

    private Submission $submission;

    /** @var list<array<string, mixed>> */
    private array $options = [];

    /** @var (callable(Request): mixed)|null */
    private $responder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new FakeHostResolver([
            'crm.example.com' => ['93.184.216.34'],
            'internal.example.com' => ['10.0.0.8'],
        ]));

        $site = Site::factory()->create(['name' => 'Дилер']);
        $form = Form::factory()->for($site)->withLeadFields()->create();
        $this->profile = IntegrationProfile::factory()->create([
            'workspace_id' => $site->workspace_id,
            'base_url' => 'https://crm.example.com/api/',
            'encrypted_credentials' => ['token' => self::TOKEN],
        ]);
        $binding = SiteIntegrationBinding::factory()->for($site)->create(['integration_profile_id' => $this->profile->id, 'overrides_json' => ['dealer' => 'D-101']]);
        $this->route = FormRoute::factory()->for($form)->webhook($binding)->create([
            'settings_json' => ['method' => 'PUT', 'path' => '/leads', 'headers' => [['name' => 'X-Source', 'value' => 'landflow']]],
            'mapping_json' => [
                ['target' => 'client.phone', 'source' => 'field.phone', 'missing' => 'omit'],
                ['target' => 'dealer', 'source' => 'override.dealer', 'missing' => 'omit'],
            ],
        ]);
        $this->submission = Submission::factory()->for($form)->create([
            'payload' => [['key' => 'phone', 'type' => 'phone', 'label' => 'Телефон', 'value' => '+7 999 111-22-33']],
        ]);
    }

    public function test_webhook_sends_mapped_json_with_auth_and_idempotency_key_through_the_pinned_policy(): void
    {
        $this->fakeResponses(fn () => Http::response('{"ok":true,"secret":"provider-body-secret"}', 201, ['Content-Type' => 'application/json; charset=utf-8', 'X-Request-Id' => 'req-42']));

        $this->deliver();

        $delivery = SubmissionDelivery::query()->sole();
        $this->assertSame([DeliveryStatus::Delivered, 201], [$delivery->status, $delivery->last_http_status]);

        Http::assertSent(function (Request $request) use ($delivery): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://crm.example.com/api/leads'
                && $request->header('Authorization') === ['Bearer '.self::TOKEN]
                && $request->header('Idempotency-Key') === [$delivery->public_id]
                && $request->header('X-Source') === ['landflow']
                && $request->header('Content-Type') === ['application/json']
                && $request->data() === ['client' => ['phone' => '+7 999 111-22-33'], 'dealer' => 'D-101'];
        });

        $this->assertFalse($this->options[0]['allow_redirects']);
        $this->assertSame(3, $this->options[0]['connect_timeout']);
        $this->assertSame(10, $this->options[0]['timeout']);
        $this->assertTrue($this->options[0]['verify']);
        $this->assertSame([CURLOPT_RESOLVE => ['crm.example.com:443:93.184.216.34']], $this->options[0]['curl']);
        $this->assertSame('', $this->options[0]['proxy']);
        $this->assertArrayNotHasKey('stream', $this->options[0]);
        $this->assertFalse($this->options[0]['progress'](0, 1024));
        $this->assertTrue($this->options[0]['progress'](0, 1048577));

        $attempt = SubmissionDeliveryAttempt::query()->sole();
        $this->assertSame(['response_bytes' => 43, 'content_type' => 'application/json', 'request_id' => 'req-42'], $attempt->response_summary_json);

        $stored = json_encode([SubmissionDelivery::query()->toBase()->get(), SubmissionDeliveryAttempt::query()->toBase()->get()], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::TOKEN, $stored);
        $this->assertStringNotContainsString('provider-body-secret', $stored);
        $this->assertStringNotContainsString('+7 999', $stored);
    }

    public function test_basic_and_api_key_auth_are_built_from_encrypted_credentials(): void
    {
        $this->fakeResponses(fn () => Http::response('', 204));
        $this->profile->forceFill(['auth_type' => IntegrationAuthType::Basic, 'encrypted_credentials' => ['username' => 'dealer', 'password' => 'p@ss']])->save();
        $this->deliver();

        Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === ['Basic '.base64_encode('dealer:p@ss')]);

        $this->profile->forceFill(['auth_type' => IntegrationAuthType::ApiKeyHeader, 'encrypted_credentials' => ['token' => self::TOKEN], 'settings_json' => ['api_key_header' => 'X-Api-Key']])->save();
        $this->deliver(Submission::factory()->for($this->submission->form)->create());

        Http::assertSent(fn (Request $request): bool => $request->header('X-Api-Key') === [self::TOKEN] && ! $request->hasHeader('Authorization'));
        $this->assertSame([DeliveryStatus::Delivered], SubmissionDelivery::query()->pluck('status')->unique()->values()->all());
    }

    public function test_503_is_retried_with_the_same_idempotency_key_and_401_fails(): void
    {
        $keys = [];
        $this->fakeResponses(function (Request $request) use (&$keys) {
            $keys[] = $request->header('Idempotency-Key')[0];

            return count($keys) === 1 ? Http::response('busy', 503) : Http::response('ok', 200);
        });

        $this->deliver();
        $delivery = SubmissionDelivery::query()->sole();
        $this->assertSame([DeliveryStatus::RetryScheduled, 'http_503'], [$delivery->status, $delivery->last_error_code]);

        $this->travel(61)->seconds();
        Artisan::call('integrations:dispatch-due-deliveries');

        $this->assertSame(DeliveryStatus::Delivered, $delivery->fresh()?->status);
        $this->assertSame([$delivery->public_id, $delivery->public_id], $keys);

        $this->fakeResponses(fn () => Http::response('{"error":"bad token '.self::TOKEN.'"}', 401));
        $this->deliver(Submission::factory()->for($this->submission->form)->create());

        $failed = SubmissionDelivery::query()->latest('id')->firstOrFail();
        $this->assertSame([DeliveryStatus::Failed, 'http_401', null], [$failed->status, $failed->last_error_code, $failed->next_retry_at]);
        $this->assertStringNotContainsString(self::TOKEN, (string) $failed->last_error_message_safe);
    }

    public function test_redirects_are_failures_and_never_followed(): void
    {
        $this->fakeResponses(fn () => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']));

        $this->deliver();

        $delivery = SubmissionDelivery::query()->sole();
        $this->assertSame([DeliveryStatus::Failed, 'redirect_not_followed'], [$delivery->status, $delivery->last_error_code]);
        Http::assertSentCount(1);
    }

    public function test_private_destinations_are_blocked_before_any_request(): void
    {
        $this->fakeResponses(fn () => Http::response('ok', 200));

        foreach (['https://10.0.0.8/hook', 'https://internal.example.com/hook', 'http://crm.example.com/hook', 'https://[::1]/'] as $url) {
            $this->profile->forceFill(['base_url' => $url])->save();
            $this->deliver(Submission::factory()->for($this->submission->form)->create());
        }

        Http::assertNothingSent();
        $this->assertSame(
            ['destination_blocked', 'destination_blocked', 'unsafe_scheme', 'destination_blocked'],
            SubmissionDelivery::query()->orderBy('id')->pluck('last_error_code')->all(),
        );
        $this->assertSame([DeliveryStatus::Failed], SubmissionDelivery::query()->pluck('status')->unique()->values()->all());
    }

    public function test_timeouts_are_transient_and_large_responses_are_capped(): void
    {
        config(['integrations.http.max_response_bytes' => 1024]);
        $this->fakeResponses(fn () => Http::response(str_repeat('x', 5000), 200));
        $this->deliver();

        $this->assertSame(['response_bytes' => 1024, 'response_truncated' => true], SubmissionDeliveryAttempt::query()->sole()->response_summary_json);

        $this->fakeResponses(fn (Request $request) => (Http::failedConnection('cURL error 28: Operation timed out after 10001 milliseconds'))($request));
        $this->deliver(Submission::factory()->for($this->submission->form)->create());

        $timedOut = SubmissionDelivery::query()->latest('id')->firstOrFail();
        $this->assertSame([DeliveryStatus::RetryScheduled, 'timeout'], [$timedOut->status, $timedOut->last_error_code]);
    }

    public function test_failure_logs_carry_only_safe_identifiers(): void
    {
        Log::spy();
        $this->fakeResponses(fn () => Http::response('{"token":"'.self::TOKEN.'"}', 500));

        $this->deliver();

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $encoded = (string) json_encode($context);

            return $message === 'delivery.attempt_failed'
                && array_keys($context) === ['delivery', 'profile', 'provider_type', 'http_status', 'error_code', 'attempt', 'status']
                && $context['profile'] === $this->profile->public_id
                && ! str_contains($encoded, self::TOKEN)
                && ! str_contains($encoded, '+7 999');
        })->once();
    }

    public function test_missing_required_mapping_value_is_a_permanent_failure(): void
    {
        $this->fakeResponses(fn () => Http::response('ok', 200));
        $this->route->forceFill(['mapping_json' => [['target' => 'utm', 'source' => 'utm.campaign', 'missing' => 'error']]])->save();

        $this->deliver();

        $delivery = SubmissionDelivery::query()->sole();
        $this->assertSame([DeliveryStatus::Failed, 'missing_required_value'], [$delivery->status, $delivery->last_error_code]);
        Http::assertNothingSent();
    }

    private function deliver(?Submission $submission = null): void
    {
        app(DeliveryDispatcher::class)->dispatchFor($submission ?? $this->submission);
    }

    /**
     * Stubs accumulate in the HTTP fake, so one stub delegates to the current responder.
     */
    private function fakeResponses(callable $respond): void
    {
        $first = $this->responder === null;
        $this->responder = $respond;

        if ($first) {
            Http::fake(function (Request $request, array $options) {
                $this->options[] = $options;

                return ($this->responder)($request);
            });
        }
    }
}
