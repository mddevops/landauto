<?php

namespace Tests\Concerns;

use App\Enums\MediaAngle;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesGalleryImage;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use Illuminate\Support\Facades\Storage;

/**
 * Platform media library rows with real 1×1 PNG files on the faked local disk. Call
 * Storage::fake('local') first.
 */
trait CreatesCatalogMedia
{
    protected static function pngBytes(): string
    {
        return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /**
     * @param  list<MediaAngle>  $angles
     */
    protected function colorSet(AutoSeries $series, string $name, array $angles = [MediaAngle::FrontThreeQuarterLeft], bool $active = true, int $sortOrder = 0): SeriesMediaSet
    {
        $set = SeriesMediaSet::factory()->forSeries($series)->create(['name' => $name, 'status' => $active, 'sort_order' => $sortOrder]);

        foreach ($angles as $angle) {
            $image = SeriesMediaImage::factory()->for($set, 'set')->create(['angle' => $angle]);
            Storage::disk(SeriesMediaImage::DISK)->put($image->path, self::pngBytes());
        }

        return $set;
    }

    protected function galleryImage(AutoSeries $series, int $sortOrder = 0): SeriesGalleryImage
    {
        $image = SeriesGalleryImage::factory()->forSeries($series)->create(['sort_order' => $sortOrder]);
        Storage::disk(SeriesGalleryImage::DISK)->put($image->path, self::pngBytes());

        return $image;
    }
}
