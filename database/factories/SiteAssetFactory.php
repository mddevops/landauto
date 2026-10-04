<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SiteAsset>
 */
class SiteAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'path' => 'site-assets/test/'.Str::lower((string) Str::ulid()).'.png',
            'original_name' => 'photo.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'width' => 800,
            'height' => 600,
        ];
    }
}
