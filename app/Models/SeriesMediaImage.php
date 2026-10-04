<?php

namespace App\Models;

use App\Enums\MediaAngle;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SeriesMediaImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable platform image of a Series media set; never a Site Asset (D-103). Replacing an
 * angle means deleting the image and uploading a new one under a new storage key.
 *
 * @property int $id
 * @property string $public_id
 * @property int $series_media_set_id
 * @property MediaAngle $angle
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SeriesMediaSet $set
 */
#[Fillable(['original_name'])]
#[Hidden(['id', 'series_media_set_id', 'path'])]
class SeriesMediaImage extends Model
{
    /** @use HasFactory<SeriesMediaImageFactory> */
    use HasFactory, HasImmutablePublicId;

    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'angle' => MediaAngle::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SeriesMediaImage $image): void {
            if ($image->isDirty(['series_media_set_id', 'angle', 'path', 'mime_type', 'size_bytes', 'width', 'height'])) {
                throw new LogicException('Series media images are immutable; upload a new image instead.');
            }
        });
    }

    /**
     * @return BelongsTo<SeriesMediaSet, $this>
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(SeriesMediaSet::class, 'series_media_set_id');
    }

    public function url(): string
    {
        return route('catalog-media.show', $this, false);
    }
}
