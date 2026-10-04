<?php

namespace Tests\Feature\Catalog;

use App\Models\Catalog\AutoCharacteristicValue;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoOptionValue;
use App\Models\Catalog\AutoSeries;
use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class CatalogDemoSeederTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_demo_catalog_is_complete_and_idempotent(): void
    {
        $this->seed(CatalogDemoSeeder::class);
        $this->seed(CatalogDemoSeeder::class);

        $series = AutoSeries::query()->available()->with('generation.model.mark')->sole();
        $this->assertSame(['Kia', 'Rio', 'IV Рестайлинг', 'Седан'], [
            $series->generation->model->mark->name, $series->generation->model->name, $series->generation->name, $series->name,
        ]);
        $this->assertSame(6, AutoEquipment::query()->available()->count());
        $this->assertSame(24, AutoCharacteristicValue::query()->count());
        $this->assertSame(2 * (3 + 5 + 5), AutoOptionValue::query()->count());
    }
}
