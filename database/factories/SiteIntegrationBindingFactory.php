<?php

namespace Database\Factories;

use App\Enums\IntegrationStatus;
use App\Models\IntegrationProfile;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteIntegrationBinding>
 */
class SiteIntegrationBindingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'integration_profile_id' => fn (array $attributes) => IntegrationProfile::factory()->create([
                'workspace_id' => Site::query()->whereKey($attributes['site_id'])->value('workspace_id'),
            ])->id,
            'overrides_json' => ['site_id' => '101'],
            'status' => IntegrationStatus::Active,
        ];
    }
}
