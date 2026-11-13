<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoModification>
 */
class AutoModificationFactory extends Factory
{
    protected $model = AutoModification::class;

    public function definition(): array
    {
        return [
            'series_id' => AutoSeries::factory(),
            'external_id' => fake()->unique()->numberBetween(40_000_000, 49_999_999),
            'name' => '1.6 AT (123 л.с.)',
            'engine_volume' => '1.6',
            'engine_power' => 123,
            'engine' => 'бензин',
            'transmission' => 'Автомат',
            'drive' => 'Передний',
            'consumption_100_km' => '6.6',
            'acceleration_0_100' => '11.2',
            'status' => 1,
        ];
    }
}
