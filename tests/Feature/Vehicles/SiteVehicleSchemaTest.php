<?php

namespace Tests\Feature\Vehicles;

use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoSeries;
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
        $this->assertTrue(Schema::hasTable('vehicle_colors'));
        $this->assertTrue(Schema::hasTable('vehicle_photos'));
        $this->assertFalse(Schema::hasTable('site_vehicle_media_sets'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('site_vehicles'));

        $series = AutoSeries::factory()->create();
        $site = Site::factory()->create();
        $vehicle = SiteVehicle::factory()->for($site)->forSeries($series)->create(['sort_order' => 2]);
        SiteVehicle::factory()->for($site)->create(['sort_order' => 1]);
        SiteVehicle::factory()->create();

        $this->assertSame($series->ref(), $vehicle->catalog_series_ref);
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

        foreach (['1:2', 'not-a-ref', '01hzzzzzzzzzzzzzzzzzzzzzzz'] as $ref) {
            try {
                SiteVehicle::factory()->create(['catalog_series_ref' => $ref]);
                $this->fail('Unknown Series must be rejected.');
            } catch (InvalidCatalogDataException) {
                $this->assertSame(1, SiteVehicle::query()->count());
            }
        }

        foreach ([
            fn () => $vehicle->forceFill(['catalog_series_ref' => AutoSeries::factory()->create()->ref()])->save(),
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
}
