<?php

namespace Database\Factories;

use App\Enums\SiteType;
use App\Enums\TemplateOwnerScope;
use App\Models\DeveloperProfile;
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
            'owner_scope' => TemplateOwnerScope::Platform,
            'developer_profile_id' => null,
            'site_types' => [SiteType::MultiPage->value, SiteType::Landing->value],
            'is_official' => true,
        ];
    }

    public function forSiteTypes(SiteType ...$types): static
    {
        return $this->state(['site_types' => array_map(fn (SiteType $type): string => $type->value, $types)]);
    }

    /**
     * With a published Template Version without content, like the seeded official Templates.
     */
    public function published(): static
    {
        return $this->afterCreating(function (Template $template): void {
            $template->versions()->create(['version' => '1.0.0']);
        });
    }

    public function developer(?DeveloperProfile $profile = null): static
    {
        return $this->state([
            'owner_scope' => TemplateOwnerScope::Developer,
            'developer_profile_id' => $profile ?? DeveloperProfile::factory(),
            'is_official' => false,
        ]);
    }
}
