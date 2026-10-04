<?php

namespace Database\Factories;

use App\Models\PublishedVersion;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublishedVersion>
 */
class PublishedVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'version_number' => fn (array $attributes) => (int) PublishedVersion::query()
                ->where('site_id', $attributes['site_id'])->max('version_number') + 1,
            'public_manifest_json' => ['pages' => []],
            'draft_snapshot_json' => ['pages' => []],
            'manifest_hash' => hash('sha256', '{"pages":[]}'),
        ];
    }
}
