<?php

namespace Tests\Feature\Vehicles;

use App\Automotive\VehicleMediaResolver;
use App\Enums\MediaAngle;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteVehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class VehicleMediaResolverTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_site_selection_wins_and_keeps_the_selected_order(): void
    {
        $series = AutoSeries::factory()->create();
        $white = $this->setWithImages($series, 'Белый', [MediaAngle::Side, MediaAngle::Front]);
        $black = $this->setWithImages($series, 'Чёрный', [MediaAngle::FrontThreeQuarter]);
        $vehicle = SiteVehicle::factory()->forSeries($series)->create();
        $vehicle->selectMediaSets([$black->public_id, $white->public_id]);

        $resolved = app(VehicleMediaResolver::class)->resolve($vehicle->fresh() ?? $vehicle);

        $this->assertSame(VehicleMediaResolver::SOURCE_SITE, $resolved['source']);
        $this->assertSame(['Чёрный', 'Белый'], array_column($resolved['sets'], 'name'));
        $this->assertSame(['front', 'side'], array_column($resolved['sets'][1]['images'], 'angle'));
        $this->assertStringStartsWith('/media/series/', $resolved['sets'][1]['images'][0]['url']);
    }

    public function test_falls_back_to_active_global_sets_when_the_site_selected_nothing_usable(): void
    {
        $series = AutoSeries::factory()->create();
        $white = $this->setWithImages($series, 'Белый', [MediaAngle::Front], sortOrder: 2);
        $this->setWithImages($series, 'Красный', [MediaAngle::Front], sortOrder: 1);
        $this->setWithImages($series, 'Выключен', [MediaAngle::Front], active: false);
        SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Без изображений']);
        $this->setWithImages(AutoSeries::factory()->create(), 'Чужой', [MediaAngle::Front]);
        $vehicle = SiteVehicle::factory()->forSeries($series)->create();
        $resolver = app(VehicleMediaResolver::class);

        $resolved = $resolver->resolve($vehicle);
        $this->assertSame(VehicleMediaResolver::SOURCE_GLOBAL, $resolved['source']);
        $this->assertSame(['Красный', 'Белый'], array_column($resolved['sets'], 'name'));

        $vehicle->selectMediaSets([$white->public_id]);
        $white->update(['status' => false]);
        $resolved = $resolver->resolve($vehicle->fresh() ?? $vehicle);
        $this->assertSame(VehicleMediaResolver::SOURCE_GLOBAL, $resolved['source']);
        $this->assertSame(['Красный'], array_column($resolved['sets'], 'name'));
    }

    public function test_vehicle_without_any_prepared_media_resolves_empty_and_many_uses_bulk_queries(): void
    {
        $empty = SiteVehicle::factory()->create();
        $series = AutoSeries::factory()->create();
        $this->setWithImages($series, 'Белый', [MediaAngle::Front]);
        $vehicles = SiteVehicle::factory()->count(3)->sequence(fn () => ['catalog_series_public_id' => $series->public_id])
            ->state(fn () => ['site_id' => Site::factory()])
            ->create();

        DB::enableQueryLog();
        $resolved = app(VehicleMediaResolver::class)->resolveMany($vehicles->push($empty));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['source' => null, 'sets' => []], $resolved[$empty->public_id]);
        $this->assertSame(VehicleMediaResolver::SOURCE_GLOBAL, $resolved[$vehicles->first()?->public_id]['source']);
        $this->assertLessThanOrEqual(3, $queries);
    }

    /**
     * @param  list<MediaAngle>  $angles
     */
    private function setWithImages(AutoSeries $series, string $name, array $angles, bool $active = true, int $sortOrder = 0): SeriesMediaSet
    {
        $set = SeriesMediaSet::factory()->forSeries($series)->create(['name' => $name, 'status' => $active, 'sort_order' => $sortOrder]);

        foreach ($angles as $angle) {
            SeriesMediaImage::factory()->for($set, 'set')->create(['angle' => $angle]);
        }

        return $set;
    }
}
