<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutoModel>
 */
class AutoModelFactory extends Factory
{
    protected $model = AutoModel::class;

    public function definition(): array
    {
        return [
            'mark_id' => AutoMark::factory(),
            'external_id' => fake()->unique()->numberBetween(10_000_000, 19_999_999),
            'name' => 'Модель '.Str::upper(Str::random(3)),
            'url' => 'model-'.Str::lower(Str::random(8)),
            'status' => 1,
        ];
    }
}
