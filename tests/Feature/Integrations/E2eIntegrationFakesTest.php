<?php

namespace Tests\Feature\Integrations;

use App\Integrations\Http\HostResolver;
use App\Integrations\Http\OutboundHttpPolicy;
use App\Integrations\Http\OutboundRequestRejected;
use App\Integrations\Testing\E2eIntegrationFakes;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class E2eIntegrationFakesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostResolver::class, new E2eIntegrationFakes);
        E2eIntegrationFakes::install();
    }

    public function test_fakes_are_off_unless_explicitly_enabled_in_testing_or_e2e(): void
    {
        $this->assertFalse(config('integrations.e2e_fake'));
        $this->assertSame('testing', $this->app->environment());
    }

    public function test_real_ssrf_policy_still_refuses_the_private_host_and_unknown_hosts(): void
    {
        $policy = app(OutboundHttpPolicy::class);

        $this->assertSame('93.184.216.34', $policy->check('https://hooks.e2e.test/leads')->ip);

        foreach (['https://private.e2e.test/' => 'destination_blocked', 'https://example.com/' => 'dns_error', 'https://127.0.0.1/' => 'destination_blocked', 'https://0x7f000001/' => 'unsafe_url'] as $url => $code) {
            try {
                $policy->check($url);
                $this->fail("{$url} was accepted.");
            } catch (OutboundRequestRejected $exception) {
                $this->assertSame($code, $exception->errorCode);
            }
        }
    }

    public function test_fake_crm_contract(): void
    {
        $this->assertSame(200, Http::withToken('t')->post('https://hooks.e2e.test')->status());
        $this->assertSame(401, Http::post('https://hooks.e2e.test')->status());

        $this->assertSame(201, Http::post('https://hooks.e2e.test/leads', ['phone' => '+7 900', 'price' => 100, 'dealer' => 'D-1'])->status());
        $this->assertSame(422, Http::post('https://hooks.e2e.test/leads', ['phone' => '+7 900', 'price' => '100', 'dealer' => 'D-1'])->status());
        $this->assertSame(422, Http::post('https://hooks.e2e.test/leads', ['phone' => '+7 900', 'price' => 100])->status());

        $flaky = fn (string $key): int => Http::withHeaders(['Idempotency-Key' => $key])->post('https://hooks.e2e.test/flaky')->status();
        $this->assertSame([503, 200, 200, 503], [$flaky('a'), $flaky('a'), $flaky('a'), $flaky('b')]);

        $this->assertSame(401, Http::post('https://hooks.e2e.test/unauthorized')->status());
        $this->assertSame(404, Http::post('https://hooks.e2e.test/other')->status());
    }
}
