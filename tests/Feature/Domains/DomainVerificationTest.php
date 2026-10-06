<?php

namespace Tests\Feature\Domains;

use App\Domains\Dns\DnsResolver;
use App\Domains\Dns\FakeDnsResolver;
use App\Domains\DomainVerifier;
use App\Enums\DomainRoutingStatus;
use App\Enums\DomainVerificationStatus;
use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DomainVerificationTest extends TestCase
{
    use RefreshDatabase;

    private FakeDnsResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        config(['domains.cname_target' => 'domains.landflow.test', 'domains.ipv4' => '203.0.113.10', 'domains.ipv6' => '2001:db8::10']);
        $this->dns = $this->app->make(DnsResolver::class);
    }

    public function test_correct_txt_verifies_ownership_and_wrong_txt_fails(): void
    {
        $domain = $this->domain('dealer.ru');

        $this->dns->set('txt', '_landflow-verification.dealer.ru', ['landflow-site-verification=wrong']);
        $this->verify($domain);
        $this->assertSame(DomainVerificationStatus::Failed, $domain->verification_status);
        $this->assertSame('txt_mismatch', $domain->last_error_code);

        $this->dns->set('txt', '_landflow-verification.dealer.ru', ['other', '"'.$domain->verificationRecordValue().'"']);
        $this->verify($domain);
        $this->assertSame(DomainVerificationStatus::Verified, $domain->verification_status);
        $this->assertNotNull($domain->verified_at);
    }

    public function test_missing_txt_keeps_ownership_pending(): void
    {
        $domain = $this->verify($this->domain('dealer.ru'));

        $this->assertSame(DomainVerificationStatus::Pending, $domain->verification_status);
        $this->assertSame('txt_missing', $domain->last_error_code);
        $this->assertNotNull($domain->last_checked_at);
    }

    public function test_a_record_to_the_ingress_verifies_routing(): void
    {
        $domain = $this->domain('dealer.ru');
        $this->dns->set('a', 'dealer.ru', ['203.0.113.10']);

        $this->assertSame(DomainRoutingStatus::Verified, $this->verify($domain)->routing_status);
        $this->assertNotNull($domain->routing_verified_at);
    }

    public function test_cname_chain_to_the_target_verifies_routing(): void
    {
        $domain = $this->domain('www.dealer.ru');
        $this->dns->set('cname', 'www.dealer.ru', ['edge.dealer.ru']);
        $this->dns->set('cname', 'edge.dealer.ru', ['domains.landflow.test']);

        $this->assertSame(DomainRoutingStatus::Verified, $this->verify($domain)->routing_status);
    }

    public function test_wrong_routing_is_misconfigured_independently_of_ownership(): void
    {
        $domain = $this->domain('dealer.ru');
        $this->dns->set('txt', '_landflow-verification.dealer.ru', [$domain->verificationRecordValue()]);
        $this->dns->set('a', 'dealer.ru', ['203.0.113.10', '198.51.100.7']);

        $this->verify($domain);

        $this->assertSame(DomainVerificationStatus::Verified, $domain->verification_status);
        $this->assertSame(DomainRoutingStatus::Misconfigured, $domain->routing_status);
        $this->assertSame('routing_mismatch', $domain->last_error_code);
        $this->assertSame('dns_misconfigured', $domain->state());
        $this->assertStringNotContainsString('198.51.100.7', (string) $domain->last_error_message_safe);
    }

    public function test_foreign_aaaa_or_cname_breaks_routing(): void
    {
        $domain = $this->domain('dealer.ru');
        $this->dns->set('a', 'dealer.ru', ['203.0.113.10']);
        $this->dns->set('aaaa', 'dealer.ru', ['2001:db8::99']);
        $this->assertSame(DomainRoutingStatus::Misconfigured, $this->verify($domain)->routing_status);

        $this->dns->set('aaaa', 'dealer.ru', ['2001:0db8:0000::10']);
        $this->assertSame(DomainRoutingStatus::Verified, $this->verify($domain)->routing_status);

        $other = $this->domain('shop.dealer.ru');
        $this->dns->set('cname', 'shop.dealer.ru', ['parking.example.net']);
        $this->assertSame(DomainRoutingStatus::Misconfigured, $this->verify($other)->routing_status);
    }

    public function test_routing_needs_a_configured_ingress(): void
    {
        config(['domains.cname_target' => '', 'domains.ipv4' => '']);
        $domain = $this->domain('dealer.ru');
        $this->dns->set('txt', '_landflow-verification.dealer.ru', [$domain->verificationRecordValue()]);
        $this->dns->set('a', 'dealer.ru', ['203.0.113.10']);

        $this->verify($domain);

        $this->assertSame(DomainRoutingStatus::Pending, $domain->routing_status);
        $this->assertSame('ingress_not_configured', $domain->last_error_code);
    }

    public function test_ownership_stays_verified_and_routing_is_rechecked(): void
    {
        $domain = $this->domain('dealer.ru');
        $this->dns->set('txt', '_landflow-verification.dealer.ru', [$domain->verificationRecordValue()]);
        $this->dns->set('a', 'dealer.ru', ['203.0.113.10']);
        $this->verify($domain);
        $this->assertTrue($domain->isDnsReady());

        $this->dns->forget('_landflow-verification.dealer.ru');
        $this->dns->set('a', 'dealer.ru', ['198.51.100.7']);
        $this->verify($domain);

        $this->assertSame(DomainVerificationStatus::Verified, $domain->verification_status);
        $this->assertSame(DomainRoutingStatus::Misconfigured, $domain->routing_status);
        $this->assertNull($domain->routing_verified_at);
    }

    public function test_manual_check_is_server_side_logged_and_never_uses_http(): void
    {
        Http::fake();
        Log::spy();
        [$owner, $site] = $this->owner();
        $domain = SiteDomain::factory()->for($site)->create(['hostname' => 'dealer.ru']);
        $this->dns->set('txt', '_landflow-verification.dealer.ru', [$domain->verificationRecordValue()]);
        $this->dns->set('a', 'dealer.ru', ['203.0.113.10']);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.domains.check', [$site, $domain]))
            ->assertRedirect(route('sites.domains.index', $site))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertTrue($domain->refresh()->isDnsReady());
        Http::assertNothingSent();
        Log::shouldHaveReceived('info')->with('custom_domain.ownership_verified', ['site' => $site->public_id, 'domain' => $domain->public_id, 'hostname' => 'dealer.ru']);
        Log::shouldHaveReceived('info')->with('custom_domain.routing_verified', ['site' => $site->public_id, 'domain' => $domain->public_id, 'hostname' => 'dealer.ru']);
    }

    public function test_manual_check_is_rate_limited_per_site(): void
    {
        config(['domains.check_per_minute' => 2]);
        [$owner, $site] = $this->owner();
        $domain = SiteDomain::factory()->for($site)->create();

        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id]);

        $this->post(route('sites.domains.check', [$site, $domain]))->assertRedirect();
        $this->post(route('sites.domains.check', [$site, $domain]))->assertRedirect();
        $this->from(route('sites.domains.index', $site))
            ->post(route('sites.domains.check', [$site, $domain]))
            ->assertRedirect(route('sites.domains.index', $site))
            ->assertInertiaFlash('toast.type', 'error');
    }

    public function test_manual_check_requires_permission_and_entitlement(): void
    {
        [, $site] = $this->owner();
        $domain = SiteDomain::factory()->for($site)->create();
        $designer = User::factory()->create();
        $site->workspace->addMember($designer, WorkspaceRole::Designer);

        $this->actingAs($designer)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.domains.check', [$site, $domain]))
            ->assertForbidden();
        $this->assertNull($domain->refresh()->last_checked_at);
    }

    public function test_reconcile_checks_due_domains_of_entitled_workspaces_only(): void
    {
        [, $site] = $this->owner();
        $due = SiteDomain::factory()->for($site)->create(['hostname' => 'due.ru']);
        $recent = SiteDomain::factory()->for($site)->create(['hostname' => 'recent.ru', 'last_checked_at' => now()]);
        $readyRecent = SiteDomain::factory()->for($site)->dnsReady()->create(['hostname' => 'ready.ru', 'last_checked_at' => now()->subHour()]);
        $unentitled = SiteDomain::factory()->create(['hostname' => 'free.ru']);
        $this->dns->set('a', 'due.ru', ['203.0.113.10']);

        $this->artisan('domains:reconcile')->assertSuccessful()->expectsOutput('Checked: 1, skipped: 1, SSL requested: 0.');

        $this->assertSame(DomainRoutingStatus::Verified, $due->refresh()->routing_status);
        $this->assertNull($unentitled->refresh()->last_checked_at);
        $this->assertSame($recent->last_checked_at?->toIso8601String(), $recent->refresh()->last_checked_at?->toIso8601String());
        $this->assertTrue($readyRecent->refresh()->isDnsReady());
    }

    public function test_fake_dns_command_refuses_without_the_fake_driver(): void
    {
        $this->app->instance(DnsResolver::class, $this->createStub(DnsResolver::class));

        $this->artisan('domains:fake-dns', ['name' => 'dealer.ru', '--a' => ['203.0.113.10']])->assertFailed();
    }

    private function verify(SiteDomain $domain): SiteDomain
    {
        return $this->app->make(DomainVerifier::class)->check($domain);
    }

    private function domain(string $hostname): SiteDomain
    {
        return SiteDomain::factory()->create(['hostname' => $hostname]);
    }

    /**
     * @return array{User, Site}
     */
    private function owner(): array
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::CustomDomain, true);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $workspace->addMember($owner, WorkspaceRole::Owner);

        return [$owner, Site::factory()->for($workspace)->create()];
    }
}
