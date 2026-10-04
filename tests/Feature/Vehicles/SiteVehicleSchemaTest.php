<?php

namespace Tests\Feature\Vehicles;

use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteVehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SiteVehicleSchemaTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_site_vehicles_live_in_the_main_database_and_reference_a_series(): void
    {
        $this->assertTrue(Schema::hasTable('site_vehicles'));
        $this->assertTrue(Schema::hasTable('site_vehicle_media_sets'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('site_vehicles'));

        $series = AutoSeries::factory()->create();
        $site = Site::factory()->create();
        $vehicle = SiteVehicle::factory()->for($site)->forSeries($series)->create(['sort_order' => 2]);
        SiteVehicle::factory()->for($site)->create(['sort_order' => 1]);
        SiteVehicle::factory()->create();

        $this->assertSame($series->public_id, $vehicle->catalog_series_public_id);
        $this->assertSame(2, $site->vehicles()->count());
        $this->assertSame($vehicle->public_id, $site->vehicles()->ordered()->get()->last()?->public_id);
        $this->assertArrayNotHasKey('id', $vehicle->toArray());
        $this->assertArrayNotHasKey('site_id', $vehicle->toArray());

        $this->expectException(QueryException::class);
        SiteVehicle::factory()->for($site)->forSeries($series)->create();
    }

    public function test_unknown_series_and_moving_a_vehicle_are_rejected(): void
    {
        $vehicle = SiteVehicle::factory()->create();

        try {
            SiteVehicle::factory()->create(['catalog_series_public_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz']);
            $this->fail('Unknown Series must be rejected.');
        } catch (InvalidCatalogDataException) {
            $this->assertSame(1, SiteVehicle::query()->count());
        }

        foreach ([
            fn () => $vehicle->forceFill(['catalog_series_public_id' => AutoSeries::factory()->create()->public_id])->save(),
            fn () => $vehicle->forceFill(['site_id' => Site::factory()->create()->id])->save(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Site and Series are immutable.');
            } catch (LogicException) {
                $vehicle->refresh();
            }
        }
    }

    public function test_media_selection_stores_references_to_active_sets_of_the_same_series(): void
    {
        $series = AutoSeries::factory()->create();
        $vehicle = SiteVehicle::factory()->forSeries($series)->create();
        $white = SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Белый']);
        $black = SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Чёрный']);
        $inactive = SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Старый', 'status' => false]);
        $foreign = SeriesMediaSet::factory()->create();

        $vehicle->selectMediaSets([$black->public_id, $white->public_id]);
        $this->assertSame(['Чёрный', 'Белый'], $vehicle->mediaSets()->pluck('name')->all());

        foreach ([[$foreign->public_id], [$inactive->public_id], ['not-a-ulid']] as $selection) {
            try {
                $vehicle->selectMediaSets($selection);
                $this->fail('Selection must be rejected.');
            } catch (InvalidCatalogDataException) {
                $this->assertSame(['Чёрный', 'Белый'], $vehicle->mediaSets()->pluck('name')->all());
            }
        }

        $vehicle->selectMediaSets([]);
        $this->assertSame(0, $vehicle->mediaSets()->count());
        $this->assertSame(4, SeriesMediaSet::query()->count(), 'selection never copies platform media');
    }
}
