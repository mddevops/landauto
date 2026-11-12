<?php

namespace Tests\Feature\Catalog;

use App\Catalog\AutoCatalog;
use App\Models\Catalog\AutoCharacteristicValue;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoOptionValue;
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

        $catalog = app(AutoCatalog::class);
        $series = $catalog->series(CatalogDemoSeeder::SERIES_REF);
        $this->assertNotNull($series);
        $this->assertSame(['Kia', 'Rio', 'IV Рестайлинг', 'Седан'], [
            $series->generation->model->mark->name, $series->generation->model->name, $series->generation->name, $series->name,
        ]);
        $this->assertSame(3, AutoEquipment::query()->count());
        $this->assertSame(array_values(CatalogDemoSeeder::EQUIPMENT_KEYS), AutoEquipment::query()->orderBy('external_id')->pluck('external_key')->all());
        $this->assertSame(2 * 5, AutoCharacteristicValue::query()->count());
        $this->assertSame(3 + 4 + 5, AutoOptionValue::query()->count());
    }

    public function test_it_leaves_a_real_catalog_untouched(): void
    {
        AutoMark::factory()->count(6)->create();

        $this->seed(CatalogDemoSeeder::class);

        $this->assertNull(AutoMark::query()->where('url', 'kia')->first());
    }
}
