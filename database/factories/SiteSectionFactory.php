<?php

namespace Database\Factories;

use App\Enums\SiteSectionType;
use App\Models\Site;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSection>
 */
class SiteSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'type' => SiteSectionType::Catalog,
            'title' => SiteSectionType::Catalog->label(),
            'prefix' => SiteSectionType::Catalog->defaultPrefix(),
            'enabled' => true,
            'sort_order' => 0,
        ];
    }
}
