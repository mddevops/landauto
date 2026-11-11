<?php

namespace Tests\Feature\Publishing;

use App\Enums\DomainSslStatus;
use App\Enums\Entitlement;
use App\Models\Plan;
use App\Models\PublishedPage;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Publishing\PublishSite;
use App\Publishing\Runtime\PublicBasePath;
use App\Support\SitePublicNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

/**
 * D-127: a Site is published at `{public_domain}/{number}/…`, where the number is assigned by the
 * platform and never changes; custom domains keep serving the Site at their root.
 */
class SitePublicAddressTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    public function test_every_site_gets_a_unique_twelve_digit_number_that_cannot_change(): void
    {
        $sites = Site::factory()->count(5)->create();

        foreach ($sites as $site) {
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{11}$/', $site->public_number);
            $this->assertTrue(SitePublicNumber::isWellFormed($site->public_number));
        }
        $this->assertCount(5, $sites->pluck('public_number')->unique());

        $site = $sites->first();
        $this->assertNotNull($site);
        $this->expectException(LogicException::class);
        $site->forceFill(['public_number' => '999999999999'])->save();
    }

    public function test_malformed_numbers_are_rejected(): void
    {
        foreach (['', '012345678901', '12345678901', '1234567890123', 'abcdefghijkl', '12345678901a'] as $number) {
            $this->assertFalse(SitePublicNumber::isWellFormed($number), $number);
        }
    }

    public function test_the_site_is_served_under_its_number_with_every_url_carrying_the_base_path(): void
    {
        $this->publishedSite();
        $this->storeLink();

        $response = $this->onPublicHost(fn () => $this->get('http://localhost/100000000001/'))->assertOk();
        $content = (string) $response->getContent();

        $this->assertStringNotContainsString(PublicBasePath::TOKEN, $content);
        $this->assertStringContainsString('href="/100000000001/offers"', $content);
        $this->assertStringContainsString('/100000000001/_landflow/forms/', $content);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/100000000001/">', $content);

        $this->onPublicHost(fn () => $this->get('http://localhost/100000000001'))->assertOk();
        $this->onPublicHost(fn () => $this->get('http://localhost/100000000002/'))->assertNotFound();
    }

    public function test_a_custom_domain_serves_the_same_artifacts_at_its_root(): void
    {
        $this->publishedSite();
        $this->storeLink();
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::CustomDomain, true);
        $this->workspace->plan()->associate($plan)->save();
        SiteDomain::factory()->for($this->site)->active()->create(['hostname' => 'dealer.ru', 'is_primary' => true, 'ssl_status' => DomainSslStatus::Active]);

        $content = (string) $this->onPublicHost(fn () => $this->get('http://dealer.ru/'))->assertOk()->getContent();

        $this->assertStringNotContainsString(PublicBasePath::TOKEN, $content);
        $this->assertStringNotContainsString('100000000001', $content);
        $this->assertStringContainsString('href="/offers"', $content);
        $this->assertStringContainsString('"/_landflow/forms/', $content);
        $this->onPublicHost(fn () => $this->get('http://localhost/100000000001/offers?a=b'))
            ->assertStatus(301)
            ->assertRedirect('http://dealer.ru/offers?a=b');
    }

    public function test_robots_on_the_number_path_keeps_the_site_form_endpoint_out_of_the_index(): void
    {
        $this->publishedSite();

        $this->onPublicHost(fn () => $this->get('http://localhost/100000000001/robots.txt'))
            ->assertOk()
            ->assertSee('Disallow: /100000000001/_landflow/forms/');
    }

    public function test_cached_routes_keep_application_public_and_custom_hosts_apart(): void
    {
        // Production-like hosts: `route:cache` matches host and path as one subject (`host.path`),
        // so the custom-host requirement must not swallow the application or public host there.
        config(['app.url' => 'https://app.landflow.test', 'publishing.public_domain' => 'my.landflow.test']);
        $original = app('router');
        $router = new Router(app('events'), app());
        app()->instance('router', $router);
        Route::clearResolvedInstance('router');

        try {
            $router->group([], base_path('routes/public.php'));
            $router->get('login', fn () => '')->name('login');
            $router->get('dashboard', fn () => '')->name('dashboard');

            $compiled = $router->getRoutes()->compile();
            $routes = (new CompiledRouteCollection($compiled['compiled'], $compiled['attributes']))
                ->setRouter($router)
                ->setContainer(app());
            $name = fn (string $url, string $method = 'GET'): ?string => $routes->match(Request::create($url, $method))->getName();

            $this->assertSame('login', $name('https://app.landflow.test/login'));
            $this->assertSame('dashboard', $name('https://app.landflow.test/dashboard'));
            $this->assertSame('login', $name('http://10.0.0.15/login'));
            $this->assertSame('public.page', $name('https://my.landflow.test/100000000001/'));
            $this->assertSame('public.page', $name('https://my.landflow.test/100000000001/offers'));
            $this->assertSame('public.forms', $name('https://my.landflow.test/100000000001/_landflow/forms/01ARZ3NDEKTSV4RRFFQ69G5FAV/01ARZ3NDEKTSV4RRFFQ69G5FAV', 'POST'));
            $this->assertSame('public.root.missing', $name('https://my.landflow.test/'));
            $this->assertSame('public.root.missing', $name('https://my.landflow.test/login'));
            $this->assertSame('public.custom.page', $name('https://dealer.ru/'));
            $this->assertSame('public.custom.missing', $name('https://dealer.ru/sites/abc/designer'));
            $this->assertSame('public.custom.sitemap', $name('https://www.dealer.ru/sitemap.xml'));
        } finally {
            app()->instance('router', $original);
            Route::clearResolvedInstance('router');
        }
    }

    private function publishedSite(): void
    {
        $this->buildPublishableSite();
        $this->withPublicNumber($this->site);
        $this->actAsMember($this->owner);
        $this->assertTrue(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());
    }

    /**
     * The fake renderer emits no page links; give the stored home page one, as the real renderer
     * does. Artifacts are immutable through the model, so the row is updated directly.
     */
    private function storeLink(): void
    {
        $page = PublishedPage::query()->where('page_public_id', $this->home->public_id)->sole();
        PublishedPage::query()->whereKey($page->id)->update([
            'rendered_html' => $page->rendered_html.'<a href="'.PublicBasePath::prefix('/offers').'">Предложения</a>',
        ]);
    }
}
