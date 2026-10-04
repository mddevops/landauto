<?php

namespace Database\Factories;

use App\Models\BlockDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlockDefinition>
 */
class BlockDefinitionFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'is_official' => true,
        ];
    }
}
