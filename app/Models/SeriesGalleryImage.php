<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SeriesGalleryImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Platform gallery photo of a catalog Series (interior, details); copied to customer vehicles.
 *
 * @property int $id
 * @property string $public_id
 * @property string $catalog_series_ref
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['original_name', 'sort_order'])]
#[Hidden(['id', 'path'])]
class SeriesGalleryImage extends Model
{
    /** @use HasFactory<SeriesGalleryImageFactory> */
    use HasFactory, HasImmutablePublicId;

    public const DISK = SeriesMediaImage::DISK;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'sort_order' => 'integer'];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function url(): string
    {
        return route('catalog-gallery.show', $this, false);
    }
}
