<?php

namespace Database\Seeders;

use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoOption;
use App\Models\Catalog\AutoSeries;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Small auto.ru-shaped catalog branch (Kia Rio IV Рестайлинг Седан) for empty local databases,
 * tests and E2E. Demo values, not a manufacturer specification. Idempotent; writes only to the
 * `catalog` connection and never runs in production or over the real auto.ru data.
 */
class CatalogDemoSeeder extends Seeder
{
    public const SERIES_REF = '21028015:21028065';

    /** A catalog with more marks holds the real auto.ru data, which must not be touched. */
    private const MAX_DEMO_MARKS = 5;

    public const EQUIPMENT_KEYS = [
        'classic' => '21028065_21028115_21028097',
        'comfort' => '21028065_21028116_21028097',
        'prestige' => '21028065_21028117_21028098',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CatalogDemoSeeder must not run in production.');
        }

        if (AutoMark::query()->count() > self::MAX_DEMO_MARKS) {
            return;
        }

        DB::connection('catalog')->transaction(function (): void {
            $mark = AutoMark::query()->updateOrCreate(['url' => 'kia'], [
                'external_id' => 90_000_001, 'name' => 'Kia', 'name_ru' => 'Киа', 'country' => 'Корея', 'popular' => 1, 'status' => 1, 'ordering' => 1,
            ]);
            $model = AutoModel::query()->updateOrCreate(['mark_id' => $mark->id, 'url' => 'rio'], [
                'external_id' => 91_000_001, 'name' => 'Rio', 'name_ru' => 'Рио', 'class' => 'B', 'year_from' => 2000, 'status' => 1,
            ]);
            $generation = AutoGeneration::query()->updateOrCreate(['model_id' => $model->id, 'external_id' => 21_028_015], [
                'name' => 'IV Рестайлинг', 'year_from' => 2020, 'year_to' => null,
            ]);
            $series = AutoSeries::query()->updateOrCreate(['generation_id' => $generation->id, 'external_id' => 21_028_065], [
                'model_id' => $model->id, 'name' => 'Седан', 'url' => 'https://auto.ru/catalog/cars/kia/rio/21028015/21028065/', 'status' => 1,
            ]);

            $manual = AutoModification::query()->updateOrCreate(['series_id' => $series->id, 'external_id' => 21_028_097], [
                'name' => '1.4 MT (100 л.с.)', 'engine_volume' => '1.4', 'engine_power' => 100, 'engine' => 'бензин',
                'transmission' => 'Механика', 'drive' => 'Передний', 'consumption_100_km' => '5.7', 'acceleration_0_100' => '12.2', 'status' => 1,
            ]);
            $automatic = AutoModification::query()->updateOrCreate(['series_id' => $series->id, 'external_id' => 21_028_098], [
                'name' => '1.6 AT (123 л.с.)', 'engine_volume' => '1.6', 'engine_power' => 123, 'engine' => 'бензин',
                'transmission' => 'Автомат', 'drive' => 'Передний', 'consumption_100_km' => '6.6', 'acceleration_0_100' => '11.2', 'status' => 1,
            ]);

            $equipments = [
                'classic' => $this->equipment($series, $manual, 21_028_115, 'Classic'),
                'comfort' => $this->equipment($series, $manual, 21_028_116, 'Comfort'),
                'prestige' => $this->equipment($series, $automatic, 21_028_117, 'Prestige'),
            ];

            $dimensions = $this->characteristic('Размеры', null, 0);
            $engine = $this->characteristic('Двигатель', null, 1);
            $characteristics = [
                [$this->characteristic('Длина', $dimensions->id, 0), '4400', 'мм'],
                [$this->characteristic('Ширина', $dimensions->id, 1), '1740', 'мм'],
                [$this->characteristic('Клиренс', $dimensions->id, 2), '160', 'мм'],
                [$this->characteristic('Объем багажника мин/макс, л', $dimensions->id, 3), '480', 'л'],
                [$this->characteristic('Тип двигателя', $engine->id, 0), 'Бензиновый', null],
            ];

            foreach ([$manual, $automatic] as $modification) {
                foreach ($characteristics as [$characteristic, $value, $unit]) {
                    DB::connection('catalog')->table('auto_characteristic_values')->updateOrInsert(
                        ['modification_id' => $modification->id, 'characteristic_id' => $characteristic->id],
                        ['value' => $value, 'unit' => $unit],
                    );
                }
            }

            $safety = $this->option('Безопасность', null, 0);
            $comfort = $this->option('Комфорт', null, 1);
            $options = [
                'abs' => $this->option('Антиблокировочная система (ABS)', $safety->id, 0),
                'airbags' => $this->option('Фронтальные подушки безопасности', $safety->id, 1),
                'ac' => $this->option('Кондиционер', $comfort->id, 0),
                'heated' => $this->option('Подогрев передних сидений', $comfort->id, 1),
                'climate' => $this->option('Климат-контроль', $comfort->id, 2),
            ];
            $trims = [
                'classic' => ['abs' => true, 'airbags' => true, 'ac' => false],
                'comfort' => ['abs' => true, 'airbags' => true, 'ac' => true, 'heated' => true],
                'prestige' => ['abs' => true, 'airbags' => true, 'ac' => true, 'heated' => true, 'climate' => true],
            ];

            foreach ($trims as $trim => $values) {
                foreach ($values as $option => $base) {
                    DB::connection('catalog')->table('auto_option_values')->updateOrInsert(
                        ['option_id' => $options[$option]->id, 'equipment_id' => $equipments[$trim]->id],
                        ['is_base' => $base],
                    );
                }
            }
        });
    }

    private function equipment(AutoSeries $series, AutoModification $modification, int $externalId, string $name): AutoEquipment
    {
        return AutoEquipment::query()->updateOrCreate(
            ['series_id' => $series->id, 'external_key' => "{$series->external_id}_{$externalId}_{$modification->external_id}"],
            ['modification_id' => $modification->id, 'external_id' => $externalId, 'name' => $name, 'status' => 1],
        );
    }

    private function characteristic(string $name, ?int $parentId, int $sort): AutoCharacteristic
    {
        return AutoCharacteristic::query()->updateOrCreate(['parent_id' => $parentId, 'name' => $name], ['sort' => $sort]);
    }

    private function option(string $name, ?int $parentId, int $sort): AutoOption
    {
        return AutoOption::query()->updateOrCreate(['parent_id' => $parentId, 'name' => $name], ['sort' => $sort]);
    }
}
