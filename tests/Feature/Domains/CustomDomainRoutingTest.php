<?php

namespace Tests\Feature\Domains;

use App\Enums\DomainSslStatus;
use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Models\Workspace;
use App\Publishing\PublishSite;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class CustomDomainRoutingTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $this->plan = Plan::factory()->create();
        $this->plan->setEntitlement(Entitlement::CustomDomain, true);
        $this->workspace->plan()->associate($this->plan)->save();

        $offers = Page::factory()->for($this->site)->create(['slug' => 'offers', 'title' => 'Предложения', 'sort_order' => 2]);
        $this->place('cta', ['title' => 'Акция месяца'], page: $offers);
        $this->actAsMember($this->owner);
        $this->assertTrue(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());
    }

    public function test_active_primary_domain_serves_the_site_with_canonical_sitemap_and_robots_on_it(): void
    {
        $this->domain('dealer.ru', primary: true);

        $this->visit('http://dealer.ru/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="http://dealer.ru/">', false)
            ->assertSee('<meta property="og:url" content="http://dealer.ru/">', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->visit('http://dealer.ru/offers')->assertOk()->assertSee('<link rel="canonical" href="http://dealer.ru/offers">', false);

        $xml = simplexml_load_string((string) $this->visit('http://dealer.ru/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame(['http://dealer.ru/', 'http://dealer.ru/offers'], array_map('strval', $xml->xpath('//*[local-name()="loc"]') ?: []));

        $this->visit('http://dealer.ru/robots.txt')->assertOk()->assertSee('Sitemap: http://dealer.ru/sitemap.xml');
    }

    public function test_landflow_subdomain_redirects_permanently_to_the_primary_preserving_path_and_query(): void
    {
        $this->domain('dealer.ru', primary: true);

        $this->visit('http://dealer.localhost/offers?utm_source=ya&x=1')
            ->assertStatus(301)
            ->assertRedirect('http://dealer.ru/offers?utm_source=ya&x=1')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->visit('http://dealer.localhost/sitemap.xml')->assertStatus(301)->assertRedirect('http://dealer.ru/sitemap.xml');
    }

    public function test_alternate_custom_domain_redirects_to_the_primary_without_loops(): void
    {
        $this->domain('dealer.ru', primary: true);
        $this->domain('www.dealer.ru');

        $this->visit('http://www.dealer.ru/offers?a=b')->assertStatus(301)->assertRedirect('http://dealer.ru/offers?a=b');
        $this->visit('http://dealer.ru/offers?a=b')->assertOk();
    }

    public function test_without_custom_primary_alternates_redirect_to_the_landflow_subdomain(): void
    {
        $this->domain('dealer.ru');

        $this->visit('http://dealer.ru/offers')->assertStatus(301)->assertRedirect('http://dealer.localhost/offers');
        $this->visit('http://dealer.localhost/')->assertOk()->assertSee('<link rel="canonical" href="http://dealer.localhost/">', false);
    }

    public function test_domains_without_ssl_are_not_served_and_cannot_become_primary(): void
    {
        $domain = $this->domain('dealer.ru', active: false);
        $domain->forceFill(['is_primary' => true])->save();

        $this->visit('http://dealer.ru/')->assertNotFound();
        $this->visit('http://dealer.localhost/')->assertOk()->assertSee('<link rel="canonical" href="http://dealer.localhost/">', false);

        $domain->forceFill(['is_primary' => false])->save();
        $this->as($this->owner)
            ->post(route('sites.domains.primary', [$this->site, $domain]))
            ->assertSessionHasErrors('domain');
        $this->assertFalse($domain->refresh()->is_primary);
    }

    public function test_lost_entitlement_falls_back_to_the_landflow_subdomain(): void
    {
        $this->domain('dealer.ru', primary: true);
        $this->workspace->plan()->associate(Plan::factory()->create())->save();

        $this->visit('http://dealer.ru/')->assertNotFound();
        $this->visit('http://dealer.localhost/')->assertOk()->assertSee('<link rel="canonical" href="http://dealer.localhost/">', false);
    }

    public function test_removing_the_primary_falls_back_to_the_landflow_subdomain(): void
    {
        $domain = $this->domain('dealer.ru', primary: true);

        $this->as($this->owner)->delete(route('sites.domains.destroy', [$this->site, $domain]))->assertRedirect();

        $this->visit('http://dealer.localhost/')->assertOk()->assertSee('<link rel="canonical" href="http://dealer.localhost/">', false);
        $this->visit('http://dealer.ru/')->assertNotFound();
    }

    public function test_reset_primary_returns_to_the_landflow_subdomain(): void
    {
        $domain = $this->domain('dealer.ru', primary: true);

        $this->as($this->owner)->delete(route('sites.domains.reset-primary', $this->site))->assertRedirect(route('sites.domains.index', $this->site));

        $this->assertFalse($domain->refresh()->is_primary);
        $this->visit('http://dealer.localhost/')->assertOk();
        $this->visit('http://dealer.ru/')->assertStatus(301)->assertRedirect('http://dealer.localhost/');
    }

    public function test_make_primary_keeps_a_single_primary_and_needs_access(): void
    {
        $first = $this->domain('dealer.ru', primary: true);
        $second = $this->domain('www.dealer.ru');
        $designer = User::factory()->create();
        $this->workspace->addMember($designer, WorkspaceRole::Designer);

        $this->as($designer)->post(route('sites.domains.primary', [$this->site, $second]))->assertForbidden();

        $this->as($this->owner)
            ->post(route('sites.domains.primary', [$this->site, $second]))
            ->assertRedirect(route('sites.domains.index', $this->site))
            ->assertInertiaFlash('toast.message', 'Основной адрес сайта — www.dealer.ru.');

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
        $this->assertSame(1, $this->site->domains()->where('is_primary', true)->count());
        $this->visit('http://dealer.ru/')->assertStatus(301)->assertRedirect('http://www.dealer.ru/');

        $stranger = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $foreign->addMember($stranger, WorkspaceRole::Owner);
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->post(route('sites.domains.primary', [$this->site, $first]))
            ->assertNotFound();
    }

    public function test_unknown_hosts_and_application_paths_on_custom_hosts_are_safe_404s(): void
    {
        $this->domain('dealer.ru', primary: true);

        $this->visit('http://unknown-dealer.ru/')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->visit('http://dealer.ru/login')->assertNotFound();
        $this->visit('http://dealer.ru/dashboard')->assertNotFound();
        $this->visit('http://dealer.ru/sites/'.$this->site->public_id)->assertNotFound();
    }

    public function test_application_hosts_are_never_treated_as_custom_domains(): void
    {
        $this->domain('dealer.ru', primary: true);

        $this->get('http://localhost/up')->assertOk();
        $this->visit('http://127.0.0.1/up')->assertOk();
    }

    public function test_archived_site_is_not_served_on_its_custom_domain(): void
    {
        $this->domain('dealer.ru', primary: true);
        $this->site->forceFill(['status' => SiteStatus::Archived])->save();

        $this->visit('http://dealer.ru/')->assertNotFound();
    }

    public function test_site_addresses_in_the_app_use_the_primary_domain(): void
    {
        $this->domain('dealer.ru', primary: true);

        $this->as($this->owner)->get(route('sites.domains.index', $this->site))
            ->assertInertia(fn ($page) => $page
                ->where('landflowAddress', 'http://dealer.localhost/')
                ->where('primaryAddress', 'http://dealer.ru/')
                ->where('hasPrimaryDomain', true)
                ->where('domains.0.url', 'http://dealer.ru/'));
        $this->as($this->owner)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sites.0.address', 'http://dealer.ru/'));
    }

    private function domain(string $hostname, bool $primary = false, bool $active = true): SiteDomain
    {
        $factory = SiteDomain::factory()->for($this->site);
        $factory = $active ? $factory->active() : $factory->dnsReady();

        return $factory->create(['hostname' => $hostname, 'is_primary' => $primary, 'ssl_status' => $active ? DomainSslStatus::Active : DomainSslStatus::Pending]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $url): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get($url));
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
