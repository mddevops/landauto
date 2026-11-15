<?php

namespace Database\Factories;

use App\Models\BlockVersion;
use App\Models\Site;
use App\Models\SiteComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteComponent>
 */
class SiteComponentFactory extends Factory
{
    protected $model = SiteComponent::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'block_version_id' => BlockVersion::factory(),
            'sort_order' => 0,
            'is_hidden' => false,
            'state_json' => [],
        ];
    }
}
