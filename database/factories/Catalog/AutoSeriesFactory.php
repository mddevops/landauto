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
            'model_id' => fn (array $attributes): int => (int) AutoGeneration::query()->whereKey($attributes['generation_id'])->value('model_id'),
            'external_id' => fake()->unique()->numberBetween(30_000_000, 39_999_999),
            'name' => 'Седан',
            'url' => 'https://auto.ru/catalog/cars/'.Str::lower(Str::random(8)).'/',
            'status' => 1,
        ];
    }
}
