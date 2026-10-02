<?php

namespace Database\Factories;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateVersion>
 */
class TemplateVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'version' => fake()->unique()->numerify('#.#.#'),
        ];
    }
}
