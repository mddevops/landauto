<?php

namespace Tests\Feature\Publishing;

use App\Automotive\SectionPages;
use App\Enums\SiteSectionType;
use App\Enums\SiteType;
use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockVehicleStatus;
use App\Models\Catalog\AutoSeries;
use App\Models\PublishedPage;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteSection;
use App\Models\SiteStockListing;
use App\Models\StockVehicle;
use App\Publishing\PublishSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class PublishedStockSectionsTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private AutoSeries $series;

    private StockVehicle $used;

    private StockVehicle $special;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['site_type' => SiteType::MultiPage])->save();
        $this->withPublicNumber($this->site);

        $this->series = AutoSeries::query()->firstOrFail();
        $this->used = StockVehicle::factory()
            ->for($this->workspace)
            ->forSeries($this->series)
            ->used(12_000, 1)
            ->ready()
            ->create([
                'year' => 2021,
                'color_name' => 'Серый',
                'inner_id' => 'used-77',
                'availability' => StockAvailability::InStock,
                'price_minor' => 110_000_000,
            ]);
        $this->special = StockVehicle::factory()
            ->for($this->workspace)
            ->forSeries($this->series)
            ->ready()
            ->create([
                'condition' => StockCondition::New,
                'year' => 2025,
                'color_name' => 'Белый',
                'inner_id' => 'new-9',
                'status' => StockVehicleStatus::Ready,
                'availability' => StockAvailability::InStock,
                'price_minor' => 220_000_000,
            ]);
        SiteStockListing::factory()->create([
            'site_id' => $this->site->id,
            'stock_vehicle_id' => $this->used->id,
        ]);
        SiteStockListing::factory()->create([
            'site_id' => $this->site->id,
            'stock_vehicle_id' => $this->special->id,
        ]);
    }

    public function test_used_and_specials_sections_publish_ready_stock_addresses(): void
    {
        $used = SiteSection::factory()->for($this->site)->create([
            'type' => SiteSectionType::Used,
            'title' => 'С пробегом',
            'prefix' => 'used',
            'enabled' => true,
        ]);
        $specials = SiteSection::factory()->for($this->site)->create([
            'type' => SiteSectionType::Specials,
            'title' => 'Спецпредложения',
            'prefix' => 'specials',
            'enabled' => true,
        ]);
        app(SectionPages::class)->ensure($used);
        app(SectionPages::class)->ensure($specials);

        $version = $this->publish();

        $slugs = PublishedPage::query()
            ->where('published_version_id', $version->id)
            ->orderBy('sort_order')
            ->pluck('slug')
            ->all();

        $this->assertContains('used', $slugs);
        $this->assertContains('used/kia', $slugs);
        $this->assertContains('used/kia/rio', $slugs);
        $this->assertContains('used/kia/rio/used-77', $slugs);
        $this->assertContains('specials', $slugs);
        $this->assertContains('specials/kia', $slugs);
        $this->assertContains('specials/kia/rio', $slugs);
        $this->assertContains('specials/kia/rio/new-9', $slugs);

        $this->visit('/used/kia/rio/used-77')->assertOk()->assertSee('KIA Rio 2021', false);
        $this->visit('/used/kia/rio')->assertOk();
        $this->visit('/specials/kia/rio/new-9')->assertOk()->assertSee('KIA Rio 2025', false);
        $this->visit('/used/kia/used-77')->assertNotFound();
        $this->visit('/used/kia/rio/missing')->assertNotFound();

        StockVehicle::factory()
            ->for($this->workspace)
            ->forSeries($this->series)
            ->used(1_000, 1)
            ->create([
                'status' => StockVehicleStatus::Draft,
                'inner_id' => 'draft-1',
                'year' => 2019,
                'color_name' => 'Чёрный',
            ]);

        $leftOut = StockVehicle::factory()
            ->for($this->workspace)
            ->forSeries($this->series)
            ->used(2_000, 1)
            ->ready()
            ->create([
                'year' => 2020,
                'color_name' => 'Синий',
                'inner_id' => 'left-out',
            ]);

        $other = Site::factory()->for($this->workspace)->create(['site_type' => SiteType::MultiPage]);
        SiteStockListing::factory()->create([
            'site_id' => $other->id,
            'stock_vehicle_id' => $leftOut->id,
        ]);

        $this->publish();
        $this->visit('/used/kia/rio/draft-1')->assertNotFound();
        $this->visit('/used/kia/rio/left-out')->assertNotFound();
    }

    public function test_used_section_creates_model_template_page(): void
    {
        $used = SiteSection::factory()->for($this->site)->create([
            'type' => SiteSectionType::Used,
            'title' => 'С пробегом',
            'prefix' => 'used',
            'enabled' => true,
        ]);
        app(SectionPages::class)->ensure($used);

        $this->assertEqualsCanonicalizing(
            ['index', 'mark', 'model', 'item'],
            $used->pages()->pluck('section_role')->map(fn ($role): string => $role->value)->all(),
        );
    }

    private function publish(): PublishedVersion
    {
        $this->actAsMember($this->owner);
        $result = app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
        $this->assertTrue($result->succeeded());

        return PublishedVersion::query()->findOrFail(Site::query()->findOrFail($this->site->id)->active_published_version_id);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $path): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get('http://localhost/100000000001'.$path));
    }
}
