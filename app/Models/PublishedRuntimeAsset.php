<?php

namespace App\Models;

use App\Enums\PublishedRuntimeAssetKind;
use App\Enums\PublishedVersionStatus;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A compiled runtime artifact of a Published Version, e.g. the deduplicated Native Block CSS
 * (ADR-009). Written only while the version is building; never updated or deleted afterwards.
 *
 * @property int $id
 * @property int $published_version_id
 * @property PublishedRuntimeAssetKind $kind
 * @property string $content
 * @property string $content_hash
 * @property int $byte_size
 * @property Carbon|null $created_at
 */
#[Hidden(['id', 'published_version_id', 'content'])]
class PublishedRuntimeAsset extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PublishedRuntimeAssetKind::class,
            'byte_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PublishedRuntimeAsset $asset): void {
            if ($asset->exists) {
                throw new LogicException('Published runtime assets are immutable.');
            }

            $status = PublishedVersion::query()->whereKey($asset->published_version_id)->first()?->status;

            if ($status !== PublishedVersionStatus::Building) {
                throw new LogicException('Runtime assets can only be added to a building Published Version.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Published runtime assets are immutable.');
        });
    }

    /**
     * @return BelongsTo<PublishedVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PublishedVersion::class, 'published_version_id');
    }
}
