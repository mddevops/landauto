<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoSeries;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutoSeries>
 */
class AutoSeriesFactory extends Factory
{
    protected $model = AutoSeries::class;

    public function definition(): array
    {
        return [
            'generation_id' => AutoGeneration::factory(),
            'name' => 'Седан',
            'url' => 'sedan-'.Str::lower(Str::random(6)),
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }
}
