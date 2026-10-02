<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'site_id' => Site::factory(),
            'title' => Str::title(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'sort_order' => 1,
            'is_home' => false,
        ];
    }

    public function home(): static
    {
        return $this->state(fn (): array => [
            'title' => Page::HOME_TITLE,
            'slug' => Page::HOME_SLUG,
            'sort_order' => 0,
            'is_home' => true,
        ]);
    }
}
