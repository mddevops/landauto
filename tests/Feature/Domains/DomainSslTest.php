<?php

namespace Tests\Feature\Domains;

use App\Domains\Dns\DnsResolver;
use App\Domains\Dns\FakeDnsResolver;
use App\Domains\DomainSsl;
use App\Domains\Ssl\CommandSslProvisioner;
use App\Domains\Ssl\FakeSslProvisioner;
use App\Domains\Ssl\NoneSslProvisioner;
use App\Domains\Ssl\SslProvisioner;
use App\Domains\Ssl\SslResult;
use App\Enums\DomainSslStatus;
use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\ProvisionDomainSsl;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class DomainSslTest extends TestCase
{
    use RefreshDatabase;

    private FakeSslProvisioner $ssl;

    protected function setUp(): void
    {
        parent::setUp();

        config(['domains.cname_target' => 'domains.landflow.test', 'domains.ipv4' => '203.0.113.10']);
        $this->ssl = new FakeSslProvisioner;
        $this->app->instance(SslProvisioner::class, $this->ssl);
    }

    public function test_certificate_is_not_requested_before_dns_is_ready(): void
    {
        Queue::fake();
        $domain = SiteDomain::factory()->for($this->entitledSite())->create();

        $this->assertFalse($this->service()->request($domain));
        $this->assertSame('Сначала подтвердите владение доменом и направьте его на Landflow.', $this->service()->ineligibility($domain));
        $this->assertSame(DomainSslStatus::Pending, $domain->refresh()->ssl_status);
        Queue::assertNothingPushed();
    }

    public function test_fake_success_activates_the_certificate_with_metadata_only(): void
    {
        $domain = SiteDomain::factory()->for($this->entitledSite())->dnsReady()->create(['last_error_code' => 'ssl_temporary', 'last_error_message_safe' => 'x']);

        $this->assertTrue($this->service()->request($domain));

        $domain->refresh();
        $this->assertSame(DomainSslStatus::Active, $domain->ssl_status);
        $this->assertTrue($domain->isActive());
        $this->assertNotNull($domain->ssl_issued_at);
        $this->assertTrue($domain->ssl_expires_at?->isFuture());
        $this->assertNull($domain->last_error_code);
        $this->assertSame(0, $domain->ssl_attempts);
    }

    public function test_temporary_failures_back_off_then_stop(): void
    {
        $domain = SiteDomain::factory()->for($this->entitledSite())->dnsReady()->create();
        $this->ssl->failNext($domain->hostname, SslResult::TEMPORARY);

        $this->service()->request($domain);
        $domain->refresh();
        $this->assertSame(DomainSslStatus::Pending, $domain->ssl_status);
        $this->assertSame(1, $domain->ssl_attempts);
        $this->assertSame('ssl_temporary', $domain->last_error_code);
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, $domain->ssl_retry_at?->timestamp, 5);
        $this->assertFalse($this->service()->request($domain), 'automatic retry waits for the backoff');

        for ($attempt = 2; $attempt <= DomainSsl::MAX_AUTOMATIC_ATTEMPTS; $attempt++) {
            $this->travelTo(now()->addHours(3));
            $this->assertTrue($this->service()->request($domain->refresh()));
        }

        $domain->refresh();
        $this->assertSame(DomainSslStatus::Failed, $domain->ssl_status);
        $this->assertSame('ssl_failed', $domain->last_error_code);
        $this->assertFalse($this->service()->request($domain), 'a failed domain needs a manual retry');
    }

    public function test_permanent_failure_needs_manual_retry_which_can_succeed(): void
    {
        $domain = SiteDomain::factory()->for($this->entitledSite())->dnsReady()->create();
        $this->ssl->failNext($domain->hostname, SslResult::PERMANENT);

        $this->service()->request($domain);
        $this->assertSame(DomainSslStatus::Failed, $domain->refresh()->ssl_status);
        $this->assertSame('failed', $domain->state());

        $this->ssl->failNext($domain->hostname, null);
        $this->assertTrue($this->service()->request($domain, manual: true));
        $this->assertSame(DomainSslStatus::Active, $domain->refresh()->ssl_status);
    }

    public function test_job_rechecks_eligibility(): void
    {
        Queue::fake();
        $site = $this->entitledSite();
        $domain = SiteDomain::factory()->for($site)->dnsReady()->create();
        $this->service()->request($domain);
        Queue::assertPushed(ProvisionDomainSsl::class);
        $this->assertSame(DomainSslStatus::Provisioning, $domain->refresh()->ssl_status);

        $site->update(['status' => SiteStatus::Archived]);
        (new ProvisionDomainSsl($domain->id))->handle($this->service());

        $this->assertSame(DomainSslStatus::Pending, $domain->refresh()->ssl_status);
        $this->assertNull($domain->ssl_issued_at);
    }

    public function test_lost_entitlement_blocks_provisioning(): void
    {
        $domain = SiteDomain::factory()->dnsReady()->create();

        $this->assertSame('Тариф пространства не включает собственные домены.', $this->service()->ineligibility($domain));
        $this->assertFalse($this->service()->request($domain, manual: true));
    }

    public function test_none_driver_records_a_safe_message(): void
    {
        $this->app->instance(SslProvisioner::class, new NoneSslProvisioner);
        $domain = SiteDomain::factory()->for($this->entitledSite())->dnsReady()->create();

        $this->assertFalse($this->service()->request($domain));

        $domain->refresh();
        $this->assertSame(DomainSslStatus::Pending, $domain->ssl_status);
        $this->assertSame('ssl_not_configured', $domain->last_error_code);
    }

    public function test_stale_provisioning_is_recovered(): void
    {
        $domain = SiteDomain::factory()->for($this->entitledSite())->dnsReady()->create(['ssl_status' => DomainSslStatus::Provisioning]);
        SiteDomain::query()->whereKey($domain->id)->update(['updated_at' => now()->subHour()]);

        $this->assertSame(1, $this->service()->recoverStale());
        $this->assertSame(DomainSslStatus::Pending, $domain->refresh()->ssl_status);
    }

    public function test_command_driver_passes_hostname_as_a_discrete_argument_and_parses_expiry(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'lf-ssl');
        config(['domains.ssl_command' => $script, 'domains.ssl_timeout' => 60]);
        Process::fake(['*' => Process::result("ok\nexpires_at=2027-01-05T10:00:00Z\n")]);

        $result = (new CommandSslProvisioner)->provision('www.dealer.ru');

        $this->assertSame(SslResult::ISSUED, $result->outcome);
        $this->assertSame('2027-01-05T10:00:00+00:00', $result->expiresAt?->toIso8601String());
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [$script, 'www.dealer.ru'] && $process->timeout === 60);
        @unlink($script);
    }

    public function test_command_driver_maps_exit_codes(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'lf-ssl');
        config(['domains.ssl_command' => $script]);

        Process::fake(['*' => Process::result('', 'rate limited', CommandSslProvisioner::EXIT_TEMPORARY)]);
        $this->assertSame(SslResult::TEMPORARY, (new CommandSslProvisioner)->provision('dealer.ru')->outcome);

        Process::fake(['*' => Process::result('', 'unauthorized', 1)]);
        $this->assertSame(SslResult::PERMANENT, (new CommandSslProvisioner)->provision('dealer.ru')->outcome);
        @unlink($script);
    }

    public function test_command_driver_refuses_invalid_hostnames_and_missing_script(): void
    {
        Process::fake();
        config(['domains.ssl_command' => sys_get_temp_dir().'/landflow-missing-script']);

        $this->assertFalse((new CommandSslProvisioner)->enabled());
        $this->assertSame(SslResult::TEMPORARY, (new CommandSslProvisioner)->provision('dealer.ru')->outcome);

        foreach (['dealer.ru; rm -rf /', '-v', 'Dealer.RU', 'localhost'] as $hostname) {
            try {
                (new CommandSslProvisioner)->provision($hostname);
                $this->fail("Accepted {$hostname}");
            } catch (InvalidArgumentException) {
                // expected
            }
        }

        Process::assertNothingRan();
    }

    public function test_no_certificate_or_key_columns_exist(): void
    {
        foreach (Schema::getColumnListing('site_domains') as $column) {
            $this->assertDoesNotMatchRegularExpression('/(private|key|pem|certificate_body|cert_body)/i', $column);
        }
    }

    public function test_manual_retry_endpoint_requires_access_and_is_rate_limited(): void
    {
        Log::spy();
        $site = $this->entitledSite();
        $owner = $site->workspace->users()->firstOrFail();
        $designer = User::factory()->create();
        $site->workspace->addMember($designer, WorkspaceRole::Designer);
        $domain = SiteDomain::factory()->for($site)->dnsReady()->create(['ssl_status' => DomainSslStatus::Failed]);
        $session = [WorkspaceContext::SESSION_KEY => $site->workspace->public_id];

        $this->actingAs($designer)->withSession($session)
            ->post(route('sites.domains.ssl', [$site, $domain]))
            ->assertForbidden();

        $this->actingAs($owner)->withSession($session)
            ->post(route('sites.domains.ssl', [$site, $domain]))
            ->assertRedirect(route('sites.domains.index', $site))
            ->assertInertiaFlash('toast.type', 'success');
        $this->assertSame(DomainSslStatus::Active, $domain->refresh()->ssl_status);
        Log::shouldHaveReceived('info')->with('custom_domain.ssl_active', ['site' => $site->public_id, 'domain' => $domain->public_id, 'hostname' => $domain->hostname]);

        for ($i = 0; $i < 2; $i++) {
            $this->post(route('sites.domains.ssl', [$site, $domain]))->assertRedirect();
        }

        $this->from(route('sites.domains.index', $site))
            ->post(route('sites.domains.ssl', [$site, $domain]))
            ->assertInertiaFlash('toast.message', 'Слишком много попыток выпуска сертификата. Повторите позже.');
    }

    public function test_dns_check_that_completes_dns_requests_the_certificate(): void
    {
        /** @var FakeDnsResolver $dns */
        $dns = $this->app->make(DnsResolver::class);
        $site = $this->entitledSite();
        $owner = $site->workspace->users()->firstOrFail();
        $domain = SiteDomain::factory()->for($site)->create(['hostname' => 'dealer.ru']);
        $dns->set('txt', '_landflow-verification.dealer.ru', [$domain->verificationRecordValue()]);
        $dns->set('a', 'dealer.ru', ['203.0.113.10']);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.domains.check', [$site, $domain]))
            ->assertInertiaFlash('toast.message', 'DNS настроен верно. Выпускаем SSL-сертификат.');

        $this->assertTrue($domain->refresh()->isActive());
        $this->assertSame('active', $domain->state());
    }

    public function test_reconcile_requests_due_certificates(): void
    {
        $site = $this->entitledSite();
        $due = SiteDomain::factory()->for($site)->dnsReady()->create(['last_checked_at' => now(), 'ssl_retry_at' => now()->subMinute()]);
        $waiting = SiteDomain::factory()->for($site)->dnsReady()->create(['last_checked_at' => now(), 'ssl_retry_at' => now()->addMinutes(10)]);

        $this->artisan('domains:reconcile')->assertSuccessful();

        $this->assertSame(DomainSslStatus::Active, $due->refresh()->ssl_status);
        $this->assertSame(DomainSslStatus::Pending, $waiting->refresh()->ssl_status);
    }

    private function service(): DomainSsl
    {
        return $this->app->make(DomainSsl::class);
    }

    private function entitledSite(): Site
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::CustomDomain, true);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $workspace->addMember($owner, WorkspaceRole::Owner);

        return Site::factory()->for($workspace)->create();
    }
}
