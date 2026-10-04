<?php

namespace App\Models;

use App\Enums\PublishedAssetKind;
use App\Enums\PublishedVersionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Records that a Published Version renders a Site Asset or Series Media image (ADR-006 §4).
 * Public file delivery is allowed only through such a reference, and a referenced file is never
 * physically deleted.
 *
 * @property int $id
 * @property int $published_version_id
 * @property PublishedAssetKind $kind
 * @property string $reference_public_id
 */
class PublishedAssetReference extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PublishedAssetKind::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PublishedAssetReference $reference): void {
            if ($reference->exists) {
                throw new LogicException('Published asset references are immutable.');
            }

            $status = PublishedVersion::query()->whereKey($reference->published_version_id)->first()?->status;

            if ($status !== PublishedVersionStatus::Building) {
                throw new LogicException('References can only be added to a building Published Version.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Published asset references are immutable.');
        });
    }

    /**
     * @return BelongsTo<PublishedVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PublishedVersion::class, 'published_version_id');
    }

    /**
     * Whether any ready or building Published Version references the given file.
     */
    public static function isReferenced(PublishedAssetKind $kind, string $publicId): bool
    {
        return self::query()
            ->where('kind', $kind)
            ->where('reference_public_id', $publicId)
            ->whereHas('version', fn (Builder $query) => $query->where('status', '!=', PublishedVersionStatus::Failed))
            ->exists();
    }
}
