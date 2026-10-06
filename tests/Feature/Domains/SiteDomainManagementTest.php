<?php

namespace Tests\Feature\Domains;

use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultWorkspacePlan;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteDomainManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitled_owner_adds_a_normalized_pending_domain(): void
    {
        Log::spy();
        [$owner, $site] = $this->siteFor(WorkspaceRole::Owner);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.domains.store', $site), ['hostname' => '  WWW.Dealer.RU.  '])
            ->assertRedirect(route('sites.domains.index', $site))
            ->assertInertiaFlash('toast.type', 'success');

        $domain = SiteDomain::query()->sole();
        $this->assertSame('www.dealer.ru', $domain->hostname);
        $this->assertSame($site->id, $domain->site_id);
        $this->assertSame('pending', $domain->state());
        $this->assertFalse($domain->is_primary);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{40}$/', $domain->verification_token);

        Log::shouldHaveReceived('info')->with('custom_domain.added', [
            'site' => $site->public_id,
            'domain' => $domain->public_id,
            'hostname' => 'www.dealer.ru',
        ]);
    }

    public function test_domains_page_shows_safe_dns_instructions(): void
    {
        config(['domains.cname_target' => 'domains.landflow.test', 'domains.ipv4' => '203.0.113.10', 'domains.ipv6' => '']);
        [$owner, $site] = $this->siteFor(WorkspaceRole::Owner);
        $domain = SiteDomain::factory()->for($site)->create(['hostname' => 'dealer.ru']);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->get(route('sites.domains.index', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/domains')
                ->where('entitled', true)
                ->where('can.manage', true)
                ->where('dns', ['cname_target' => 'domains.landflow.test', 'ipv4' => '203.0.113.10', 'ipv6' => null])
                ->where('domains.0.public_id', $domain->public_id)
                ->where('domains.0.hostname', 'dealer.ru')
                ->where('domains.0.state', 'pending')
                ->where('domains.0.verification.name', '_landflow-verification.dealer.ru')
                ->where('domains.0.verification.value', 'landflow-site-verification='.$domain->verification_token)
                ->missing('domains.0.id')
                ->missing('domains.0.site_id'));
    }

    public function test_free_plan_is_denied_by_default(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan_id' => app(DefaultWorkspacePlan::class)->resolve()->id]);
        $workspace->addMember($owner, WorkspaceRole::Owner);
        $site = Site::factory()->for($workspace)->create();

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.domains.index', $site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('entitled', false)->where('can.manage', false));

        $this->post(route('sites.domains.store', $site), ['hostname' => 'dealer.ru'])->assertForbidden();
        $this->assertSame(0, SiteDomain::query()->count());
    }

    public function test_entitlement_without_permission_is_denied(): void
    {
        foreach ([WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            [$member, $site] = $this->siteFor($role);

            $this->actingAs($member)
                ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
                ->get(route('sites.domains.index', $site))
                ->assertForbidden();
            $this->post(route('sites.domains.store', $site), ['hostname' => 'dealer.ru'])->assertForbidden();
        }

        $this->assertSame(0, SiteDomain::query()->count());
    }

    public function test_admin_with_entitlement_may_manage_domains(): void
    {
        [$admin, $site] = $this->siteFor(WorkspaceRole::Admin);

        $this->actingAs($admin)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.domains.store', $site), ['hostname' => 'admin-dealer.ru'])
            ->assertRedirect();

        $this->assertSame(1, SiteDomain::query()->count());
    }

    public function test_foreign_site_and_foreign_domain_are_not_found(): void
    {
        [$owner, $site] = $this->siteFor(WorkspaceRole::Owner);
        [, $foreignSite] = $this->siteFor(WorkspaceRole::Owner);
        $foreignDomain = SiteDomain::factory()->for($foreignSite)->create();

        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id]);

        $this->get(route('sites.domains.index', $foreignSite))->assertNotFound();
        $this->post(route('sites.domains.store', $foreignSite), ['hostname' => 'dealer.ru'])->assertNotFound();
        $this->delete(route('sites.domains.destroy', [$site, $foreignDomain]))->assertNotFound();
        $this->delete(route('sites.domains.destroy', [$foreignSite, $foreignDomain]))->assertNotFound();

        $this->assertModelExists($foreignDomain);
    }

    public function test_hostnames_are_globally_unique_case_insensitively(): void
    {
        [$owner, $site] = $this->siteFor(WorkspaceRole::Owner);
        [, $otherSite] = $this->siteFor(WorkspaceRole::Owner);
        SiteDomain::factory()->for($otherSite)->create(['hostname' => 'dealer.ru']);
        SiteDomain::factory()->for($site)->create(['hostname' => 'www.dealer.ru']);

        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id]);

        $this->post(route('sites.domains.store', $site), ['hostname' => 'DEALER.RU'])
            ->assertSessionHasErrors(['hostname' => 'Этот домен уже подключён к другому сайту Landflow.']);
        $this->post(route('sites.domains.store', $site), ['hostname' => 'www.dealer.ru.'])
            ->assertSessionHasErrors(['hostname' => 'Этот домен уже добавлен к сайту.']);

        $this->assertSame(2, SiteDomain::query()->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHostnames(): array
    {
        return [
            'scheme' => ['https://dealer.ru'],
            'path' => ['dealer.ru/cars'],
            'query' => ['dealer.ru?x=1'],
            'fragment' => ['dealer.ru#top'],
            'port' => ['dealer.ru:8080'],
            'userinfo' => ['user@dealer.ru'],
            'wildcard' => ['*.dealer.ru'],
            'empty label' => ['dealer..ru'],
            'leading dot' => ['.dealer.ru'],
            'single label' => ['dealer'],
            'label too long' => [str_repeat('a', 64).'.ru'],
            'hyphen edge' => ['-dealer.ru'],
            'underscore' => ['my_dealer.ru'],
            'space' => ['dealer .ru'],
            'idn unicode' => ['дилер.рф'],
            'punycode' => ['xn--d1acufc.xn--p1ai'],
            'ipv4' => ['192.168.0.1'],
            'localhost' => ['shop.localhost'],
            'public domain' => ['landflow.test'],
            'public subdomain' => ['dealer.landflow.test'],
            'too long' => [str_repeat('abcdefghi.', 26).'ru'],
        ];
    }

    #[DataProvider('invalidHostnames')]
    public function test_invalid_hostnames_are_rejected(string $hostname): void
    {
        config(['publishing.public_domain' => 'landflow.test']);
        [$owner, $site] = $this->siteFor(WorkspaceRole::Owner);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.domains.store', $site), ['hostname' => $hostname])
            ->assertSessionHasErrors('hostname');

        $this->assertSame(0, SiteDomain::query()->count());
    }

    public function test_idn_rejection_has_a_clear_russian_message(): void
    {
        [$owner, $site] = $this->siteFor(WorkspaceRole::Owner);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.domains.store', $site), ['hostname' => 'дилер.рф'])
            ->assertSessionHasErrors(['hostname' => 'Домены с национальными символами пока не поддерживаются. Укажите домен латиницей, например dealer.ru.']);
    }

    public function test_owner_removes_a_domain_and_designer_cannot(): void
    {
        [$owner, $site] = $this->siteFor(WorkspaceRole::Owner);
        $domain = SiteDomain::factory()->for($site)->primary()->create();
        $designer = User::factory()->create();
        $site->workspace->addMember($designer, WorkspaceRole::Designer);

        $this->actingAs($designer)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->delete(route('sites.domains.destroy', [$site, $domain]))
            ->assertForbidden();
        $this->assertModelExists($domain);

        $this->actingAs($owner)
            ->delete(route('sites.domains.destroy', [$site, $domain]))
            ->assertRedirect(route('sites.domains.index', $site));
        $this->assertModelMissing($domain);
        $this->assertNotNull($site->refresh()->subdomain);
    }

    /**
     * @return array{User, Site}
     */
    private function siteFor(WorkspaceRole $role): array
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::CustomDomain, true);
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $workspace->addMember($user, $role);

        return [$user, Site::factory()->for($workspace)->create()];
    }
}
