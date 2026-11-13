<?php

namespace Database\Factories;

use App\Models\Catalog\AutoSeries;
use App\Models\SeriesGalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Requires a migrated catalog connection.
 *
 * @extends Factory<SeriesGalleryImage>
 */
class SeriesGalleryImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'catalog_series_ref' => fn (): string => (string) AutoSeries::factory()->create()->ref(),
            'path' => 'series-gallery/'.Str::lower((string) Str::ulid()).'.png',
            'original_name' => 'gallery.png',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
            'width' => 1600,
            'height' => 900,
            'sort_order' => 0,
        ];
    }

    public function forSeries(AutoSeries $series): static
    {
        return $this->state(['catalog_series_ref' => (string) $series->ref()]);
    }
}
