<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'name' => 'I',
            'url' => 'gen-'.Str::lower(Str::random(8)),
            'year_from' => 2020,
            'year_to' => null,
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }
}
