<?php

namespace Database\Factories;

use App\Enums\BlockCategory;
use App\Models\BlockGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlockGroup>
 */
class BlockGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => Str::ucfirst(fake()->unique()->word()).' '.fake()->numberBetween(1, 999_999),
            'kind' => BlockCategory::Other,
            'sort_order' => 0,
        ];
    }
}
