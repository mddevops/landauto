<?php

namespace Tests\Feature\Catalog;

use App\Catalog\AutoCatalog;
use App\Catalog\CatalogPresenter;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class AutoCatalogTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_series_refs_resolve_by_generation_and_series_external_ids(): void
    {
        $series = AutoSeries::factory()->create();
        $ref = (string) $series->ref();
        $catalog = app(AutoCatalog::class);

        $this->assertMatchesRegularExpression('/^\d+:\d+$/', $ref);
        $this->assertSame($series->id, $catalog->series($ref)?->id);
        $this->assertNull($catalog->series("{$series->generation->external_id}:1"));
        $this->assertNull($catalog->series('../etc'));
        $this->assertNull($catalog->series(['not', 'a', 'string']));
        $this->assertSame([$ref], $catalog->seriesByRefs([$ref, $ref, 'x', 42])->keys()->all());
    }

    public function test_series_of_generations_without_external_id_are_not_referencable(): void
    {
        $generation = AutoGeneration::factory()->create(['external_id' => null]);
        $series = AutoSeries::factory()->for($generation, 'generation')->create();

        $this->assertNull($series->ref());
    }

    public function test_trims_list_named_equipments_or_the_base_row(): void
    {
        $series = AutoSeries::factory()->create();
        $withTrims = AutoModification::factory()->for($series, 'series')->create(['engine_volume' => '2.0', 'engine_power' => 150]);
        AutoEquipment::factory()->for($withTrims, 'modification')->base()->create();
        AutoEquipment::factory()->for($withTrims, 'modification')->create(['name' => 'Luxe']);
        $baseOnly = AutoModification::factory()->for($series, 'series')->create(['engine_volume' => '1.6', 'engine_power' => 110]);
        $base = AutoEquipment::factory()->for($baseOnly, 'modification')->base()->create();
        AutoModification::factory()->for($series, 'series')->create(['engine_volume' => '1.4']);

        $trims = app(CatalogPresenter::class)->trims($series);

        $this->assertCount(2, $trims);
        $this->assertSame([['key' => $base->external_key, 'name' => 'Базовая', 'base' => true, 'options_count' => 0]], $trims[0]['equipments']);
        $this->assertSame(['Luxe'], array_column($trims[1]['equipments'], 'name'));
        $this->assertSame($base->id, app(AutoCatalog::class)->equipment($series, $base->external_key)?->id);
        $this->assertNull(app(AutoCatalog::class)->equipment(AutoSeries::factory()->create(), $base->external_key));
    }

    public function test_legacy_modification_facts_come_from_characteristics(): void
    {
        $modification = AutoModification::factory()->create([
            'name' => 'Передний', 'engine_volume' => null, 'engine_power' => null, 'engine' => null,
            'transmission' => 'Передний', 'drive' => '10.7 / 6.4', 'consumption_100_km' => null, 'acceleration_0_100' => null,
        ]);

        foreach ([
            'Объем двигателя' => '1596',
            'Максимальная мощность' => '115 л.с. (85 кВт) при 6000 об/мин',
            'Тип двигателя' => 'Бензиновый',
            'Коробка передач' => 'Механика',
            'Расход топлива, город/трасса/смешанный' => '8.5/5.2/6.4',
        ] as $name => $value) {
            $characteristic = AutoCharacteristic::factory()->create(['name' => $name]);
            DB::connection('catalog')->table('auto_characteristic_values')->insert([
                'modification_id' => $modification->id, 'characteristic_id' => $characteristic->id, 'value' => $value,
            ]);
        }

        $facts = app(AutoCatalog::class)->facts([$modification])[$modification->id];

        $this->assertSame('1,6 л · 115 л.с. · Механика · Передний', $facts->label());
        $this->assertSame('1,6 л · 115 л.с. · Бензин · Механика · Передний', $facts->summary());
        $this->assertContains(['label' => 'Расход', 'value' => '6,4 л/100 км'], $facts->specs());
    }

    public function test_demo_options_and_characteristics_are_grouped_by_section(): void
    {
        $this->seed(CatalogDemoSeeder::class);
        $presenter = app(CatalogPresenter::class);
        $series = app(AutoCatalog::class)->series(CatalogDemoSeeder::SERIES_REF);
        $this->assertNotNull($series);

        $options = $presenter->options($series, CatalogDemoSeeder::EQUIPMENT_KEYS['classic']);
        $this->assertSame(['Безопасность', 'Комфорт'], array_column($options, 'group'));
        $this->assertSame(['name' => 'Кондиционер', 'availability' => 'optional'], $options[1]['items'][0]);

        $characteristics = $presenter->characteristics($series, '21028097');
        $this->assertSame(['Размеры', 'Двигатель'], array_column($characteristics, 'group'));
        $this->assertSame(['label' => 'Длина', 'value' => '4400 мм'], $characteristics[0]['items'][0]);
        $this->assertSame([], $presenter->characteristics($series, 'drop table'));
    }

    public function test_catalog_rows_are_never_written_in_production(): void
    {
        $mark = AutoMark::factory()->create();
        $this->app['env'] = 'production';

        try {
            $this->expectException(LogicException::class);
            $mark->update(['name' => 'Изменено']);
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
