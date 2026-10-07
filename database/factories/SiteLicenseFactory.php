<?php

namespace Database\Factories;

use App\Enums\SiteLicenseSource;
use App\Models\BlockDefinition;
use App\Models\Site;
use App\Models\SiteLicense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteLicense>
 */
class SiteLicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'block_definition_id' => BlockDefinition::factory(),
            'source' => SiteLicenseSource::AdminGrant,
        ];
    }
}
