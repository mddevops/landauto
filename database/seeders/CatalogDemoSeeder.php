<?php

namespace Database\Seeders;

use App\Catalog\EquipmentCharacteristics;
use App\Catalog\EquipmentOptions;
use App\Enums\Catalog\OptionAvailability;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoOption;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Small illustrative catalog branch (Kia Rio IV Рестайлинг Седан) for local demos and E2E.
 * Values are demo data, not an authoritative manufacturer specification. Idempotent: rows are
 * matched by url/code/name and never duplicated. Writes only to the `catalog` connection.
 */
class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CatalogDemoSeeder must not run in production.');
        }

        $mark = AutoMark::query()->firstOrCreate(['url' => 'kia'], ['name' => 'Kia', 'name_ru' => 'Киа', 'country' => 'Корея', 'status' => true]);
        $model = $mark->models()->firstOrCreate(['url' => 'rio'], ['name' => 'Rio', 'name_ru' => 'Рио', 'year_from' => 2011, 'status' => true]);
        $generation = $model->generations()->firstOrCreate(['url' => 'iv-restyling'], ['name' => 'IV Рестайлинг', 'year_from' => 2020, 'status' => true]);
        $series = $generation->series()->firstOrCreate(['url' => 'sedan'], ['name' => 'Седан', 'status' => true]);

        $modifications = [
            $series->modifications()->firstOrCreate(['name' => '1.4 MT 100 л.с.'], [
                'engine_volume' => 1368, 'engine_power' => '100', 'engine' => 'petrol', 'transmission' => 'manual', 'drive' => 'fwd',
                'consumption_100_km' => '5.7', 'acceleration_0_100' => '12.2', 'status' => true, 'sort_order' => 0,
            ]),
            $series->modifications()->firstOrCreate(['name' => '1.6 AT 123 л.с.'], [
                'engine_volume' => 1591, 'engine_power' => '123', 'engine' => 'petrol', 'transmission' => 'automatic', 'drive' => 'fwd',
                'consumption_100_km' => '6.6', 'acceleration_0_100' => '11.2', 'status' => true, 'sort_order' => 1,
            ]),
        ];

        $dimensions = AutoCharacteristic::query()->firstOrCreate(['code' => 'demo_dimensions'], ['name' => 'Размеры', 'sort_order' => 0]);
        $length = $dimensions->children()->firstOrCreate(['code' => 'demo_length'], ['name' => 'Длина', 'unit' => 'мм', 'sort_order' => 0]);
        $width = $dimensions->children()->firstOrCreate(['code' => 'demo_width'], ['name' => 'Ширина', 'unit' => 'мм', 'sort_order' => 1]);
        $clearance = $dimensions->children()->firstOrCreate(['code' => 'demo_clearance'], ['name' => 'Клиренс', 'unit' => 'мм', 'sort_order' => 2]);
        $trunk = $dimensions->children()->firstOrCreate(['code' => 'demo_trunk'], ['name' => 'Объём багажника', 'unit' => 'л', 'sort_order' => 3]);

        $safety = AutoOption::query()->firstOrCreate(['code' => 'demo_safety'], ['name' => 'Безопасность', 'sort_order' => 0]);
        $comfort = AutoOption::query()->firstOrCreate(['code' => 'demo_comfort'], ['name' => 'Комфорт', 'sort_order' => 1]);
        $options = [
            'abs' => $safety->children()->firstOrCreate(['code' => 'demo_abs'], ['name' => 'Антиблокировочная система (ABS)', 'sort_order' => 0]),
            'airbags' => $safety->children()->firstOrCreate(['code' => 'demo_airbags'], ['name' => 'Фронтальные подушки безопасности', 'sort_order' => 1]),
            'ac' => $comfort->children()->firstOrCreate(['code' => 'demo_ac'], ['name' => 'Кондиционер', 'sort_order' => 0]),
            'heated' => $comfort->children()->firstOrCreate(['code' => 'demo_heated_seats'], ['name' => 'Подогрев передних сидений', 'sort_order' => 1]),
            'climate' => $comfort->children()->firstOrCreate(['code' => 'demo_climate'], ['name' => 'Климат-контроль', 'sort_order' => 2]),
        ];

        $standard = OptionAvailability::Standard;
        $optional = OptionAvailability::Optional;
        $trims = [
            'Classic' => ['abs' => $standard, 'airbags' => $standard, 'ac' => $optional],
            'Comfort' => ['abs' => $standard, 'airbags' => $standard, 'ac' => $standard, 'heated' => $standard, 'climate' => $optional],
            'Prestige' => ['abs' => $standard, 'airbags' => $standard, 'ac' => $standard, 'heated' => $standard, 'climate' => $standard],
        ];
        $characteristicSync = new EquipmentCharacteristics;
        $optionSync = new EquipmentOptions;

        foreach ($modifications as $modification) {
            $position = 0;

            foreach ($trims as $name => $availability) {
                $equipment = $modification->equipments()->firstOrCreate(['name' => $name], ['status' => true, 'sort_order' => $position++]);
                $characteristicSync->sync($equipment, [
                    $length->public_id => '4400',
                    $width->public_id => '1740',
                    $clearance->public_id => '160',
                    $trunk->public_id => '480',
                ]);
                $optionSync->sync($equipment, collect($availability)
                    ->mapWithKeys(fn (OptionAvailability $value, string $key): array => [$options[$key]->public_id => $value])
                    ->all());
            }
        }
    }
}
