<?php

namespace Database\Factories;

use App\Enums\SiteType;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'site_types' => [SiteType::MultiPage->value, SiteType::Landing->value],
            'is_official' => true,
        ];
    }

    public function forSiteTypes(SiteType ...$types): static
    {
        return $this->state(['site_types' => array_map(fn (SiteType $type): string => $type->value, $types)]);
    }
}
