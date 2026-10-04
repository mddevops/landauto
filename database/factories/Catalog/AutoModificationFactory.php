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
            'name' => '1.6 AT 123 л.с.',
            'engine_volume' => 1591,
            'engine_power' => '123.00',
            'engine' => 'petrol',
            'transmission' => 'automatic',
            'drive' => 'fwd',
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }
}
