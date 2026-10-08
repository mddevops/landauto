<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Template;
use App\Models\TemplatePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplatePage>
 */
class TemplatePageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'title' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'sort_order' => 1,
            'is_home' => false,
        ];
    }

    public function home(): static
    {
        return $this->state(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0, 'is_home' => true]);
    }
}
