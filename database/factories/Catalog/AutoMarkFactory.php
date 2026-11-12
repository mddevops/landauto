<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoMark;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutoMark>
 */
class AutoMarkFactory extends Factory
{
    protected $model = AutoMark::class;

    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->numberBetween(1_000, 9_999_999),
            'name' => 'Марка '.Str::upper(Str::random(4)),
            'url' => 'mark-'.Str::lower(Str::random(8)),
            'status' => 1,
        ];
    }
}
