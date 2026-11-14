<?php

namespace Tests\Feature\Sites;

use App\Automotive\VehiclePages;
use App\Enums\SiteType;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoSeries;
use App\Models\PublishedPage;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteSection;
use App\Models\SiteVehicle;
use App\Publishing\PublishSite;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

/**
 * X-036: a Landing enables one vehicle card template Page; cards live at the Site root
 * (`/rio-iv`), the body is added only when the generation repeats (ADR-011 §7).
 */
class LandingVehicleCardTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private SiteVehicle $wagon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite(SiteType::Landing);
        $this->withPublicNumber($this->site);
        $wagon = AutoSeries::factory()->for(AutoGeneration::query()->sole(), 'generation')->create(['name' => 'Универсал']);
        $this->wagon = SiteVehicle::factory()->for($this->site)->forSeries($wagon)->create(['sort_order' => 5]);
    }

    public function test_landing_offers_only_the_vehicle_card_with_one_template_page(): void
    {
        $this->as()->get(route('sites.sections.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('types', 1)
                ->where('types.0.value', 'card')
                ->where('types.0.root', true));

        $this->as()->post(route('sites.sections.store', $this->site), ['type' => 'catalog'])->assertSessionHasErrors('type');
        $this->as()->post(route('sites.sections.store', $this->site), ['type' => 'card', 'prefix' => 'cars'])->assertSessionHasNoErrors();

        $section = $this->site->sections()->sole();
        $this->assertSame(['card', ''], [$section->type->value, $section->prefix]);
        $this->assertSame(['item'], $section->pages()->get()->map(fn ($page): string => $page->section_role->value)->all());

        $this->as()->patch(route('sites.sections.update', [$this->site, $section]), ['title' => 'Модели', 'prefix' => 'cars', 'enabled' => false])->assertSessionHasNoErrors();
        $this->assertSame(['Модели', '', false], [$section->fresh()?->title, $section->fresh()?->prefix, $section->fresh()?->enabled]);

        $this->as()->get(route('sites.sections.index', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sections.0.root', true)
                ->where('sections.0.pages.0.path', 'rio-iv-restyling'));
    }

    public function test_multi_page_site_cannot_add_the_landing_card(): void
    {
        $this->site = Site::factory()->for($this->workspace)->create(['site_type' => SiteType::MultiPage]);

        $this->as()->post(route('sites.sections.store', $this->site), ['type' => 'card'])->assertSessionHasErrors('type');
        $this->as()->get(route('sites.sections.index', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('types', fn ($types): bool => collect($types)->pluck('value')->all() === ['catalog', 'used', 'specials']));
    }

    public function test_landing_vehicles_get_root_addresses_unique_on_the_site(): void
    {
        $this->card();

        $this->as()->get(route('sites.vehicles.show', [$this->site, $this->vehicle]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('address.slug', 'rio-iv')
                ->where('address.base_url', 'http://localhost/'.$this->site->public_number.'/'));
        $this->assertSame('rio-iv-universal', $this->wagon->fresh()?->slug);

        $this->as()->patch(route('sites.vehicles.update', [$this->site, $this->wagon]), ['slug' => 'rio-iv'])
            ->assertSessionHasErrors(['slug' => 'Такой адрес уже занят на этом сайте, выберите другой.']);
        $this->as()->patch(route('sites.vehicles.update', [$this->site, $this->wagon]), ['slug' => 'search'])->assertSessionHasErrors('slug');
        $this->as()->patch(route('sites.vehicles.update', [$this->site, $this->wagon]), ['slug' => $this->home->slug])->assertSessionHasErrors('slug');
        $this->as()->patch(route('sites.vehicles.update', [$this->site, $this->wagon]), ['slug' => 'rio-wagon'])->assertSessionHasNoErrors();
    }

    public function test_preview_resolves_root_card_addresses_only(): void
    {
        $item = $this->card()->pages()->sole();

        $this->as()->get(route('sites.preview', ['site' => $this->site, 'path' => 'rio-iv']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.public_id', $item->public_id)
                ->where('catalog.root', true)
                ->where('pageContext', ['role' => 'item', 'mark' => 'kia', 'vehicle' => $this->vehicle->public_id]));

        foreach (['kia', 'kia/rio-iv', 'card/rio-iv', 'rio-iv/extra'] as $path) {
            $this->as()->get(route('sites.preview', ['site' => $this->site, 'path' => $path]))->assertNotFound();
        }
    }

    public function test_published_landing_serves_cards_at_the_root_and_redirects_renamed_ones(): void
    {
        $this->card();
        $version = $this->publish();

        $this->assertSame(
            ['', 'rio-iv', 'rio-iv-universal'],
            PublishedPage::query()->where('published_version_id', $version->id)->orderBy('sort_order')->pluck('slug')
                ->map(fn (string $slug): string => $slug === $this->home->slug ? '' : $slug)->all(),
        );

        $this->visit('/rio-iv')->assertOk()->assertSee('<title>KIA Rio IV Седан</title>', false);
        $this->visit('/kia/rio-iv')->assertNotFound();
        $this->assertContains('http://localhost/100000000001/rio-iv-universal', $this->sitemap());

        $hydration = PublishedPage::query()->where('published_version_id', $version->id)->where('slug', 'rio-iv')->sole()->hydration_json;
        $this->assertTrue($hydration['catalog']['root']);
        $this->assertSame('', $hydration['catalog']['prefix']);

        app(VehiclePages::class)->rename($this->vehicle->fresh() ?? $this->vehicle, 'rio-sedan');
        $this->publish();

        $this->visit('/rio-sedan')->assertOk();
        $this->visit('/rio-iv?utm_source=ya')->assertStatus(301)->assertHeader('Location', 'http://localhost/100000000001/rio-sedan?utm_source=ya');
    }

    private function card(): SiteSection
    {
        $this->as()->post(route('sites.sections.store', $this->site), ['type' => 'card'])->assertSessionHasNoErrors();

        return $this->site->sections()->sole();
    }

    private function publish(): PublishedVersion
    {
        $this->actAsMember($this->owner);
        $result = app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
        $this->assertTrue($result->succeeded());

        return PublishedVersion::query()->findOrFail(Site::query()->findOrFail($this->site->id)->active_published_version_id);
    }

    /**
     * @return list<string>
     */
    private function sitemap(): array
    {
        $xml = simplexml_load_string((string) $this->visit('/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($xml);

        return array_map('strval', $xml->xpath('//*[local-name()="loc"]') ?: []);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $path): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get('http://localhost/100000000001'.$path));
    }

    private function as(): self
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
