<?php

namespace Database\Factories;

use App\Enums\MediaAngle;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SeriesMediaImage>
 */
class SeriesMediaImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'series_media_set_id' => SeriesMediaSet::factory(),
            'angle' => MediaAngle::FrontThreeQuarter,
            'path' => 'series-media/test/'.Str::lower((string) Str::ulid()).'.png',
            'original_name' => 'front.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'width' => 1600,
            'height' => 900,
        ];
    }
}
