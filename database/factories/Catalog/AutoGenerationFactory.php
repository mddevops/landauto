<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoGeneration>
 */
class AutoGenerationFactory extends Factory
{
    protected $model = AutoGeneration::class;

    public function definition(): array
    {
        return [
            'model_id' => AutoModel::factory(),
            'external_id' => fake()->unique()->numberBetween(20_000_000, 29_999_999),
            'name' => 'I',
            'year_from' => 2020,
            'year_to' => null,
        ];
    }
}
