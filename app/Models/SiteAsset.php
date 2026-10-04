<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable customer image owned by one Site (ADR-003). Replacing an image creates a new asset.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['original_name'])]
#[Hidden(['id', 'site_id', 'path'])]
class SiteAsset extends Model
{
    public const DISK = 'local';

    public const MAX_KILOBYTES = 10240;

    public const MAX_SIDE = 10000;

    /** Detected MIME type => stored extension. */
    public const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** @use HasFactory<SiteAssetFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SiteAsset $asset): void {
            if ($asset->isDirty(['site_id', 'path', 'mime_type', 'size_bytes', 'width', 'height'])) {
                throw new LogicException('Site Asset files are immutable; upload a new asset instead.');
            }
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
