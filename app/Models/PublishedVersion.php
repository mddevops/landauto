<?php

namespace App\Models;

use App\Enums\PublishedVersionStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\PublishedVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An immutable published snapshot of a whole Site (ADR-006). `public_manifest_json` is the only
 * public rendering source; `draft_snapshot_json` is private and exists only for restore-to-Draft.
 * A version may move building → ready or building → failed; a ready version never changes again.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int $version_number
 * @property PublishedVersionStatus $status
 * @property array<string, mixed> $public_manifest_json
 * @property array<string, mixed> $draft_snapshot_json
 * @property string $manifest_hash
 * @property int|null $created_by
 * @property Carbon|null $ready_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'site_id', 'created_by', 'public_manifest_json', 'draft_snapshot_json'])]
class PublishedVersion extends Model
{
    /** @use HasFactory<PublishedVersionFactory> */
    use HasFactory, HasImmutablePublicId;

    /** Columns fixed at creation; only status/ready_at may change while building. */
    private const IMMUTABLE = ['site_id', 'version_number', 'public_manifest_json', 'draft_snapshot_json', 'manifest_hash', 'created_by'];

    protected $guarded = ['id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'building',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublishedVersionStatus::class,
            'public_manifest_json' => 'array',
            'draft_snapshot_json' => 'array',
            'ready_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PublishedVersion $version): void {
            if ($version->isDirty(self::IMMUTABLE)) {
                throw new LogicException('Published Version snapshot is immutable.');
            }

            if ($version->getOriginal('status') !== PublishedVersionStatus::Building) {
                throw new LogicException('Only a building Published Version may change state.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Published Versions are retained.');
        });
    }

    public function isReady(): bool
    {
        return $this->status === PublishedVersionStatus::Ready;
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The Publish attempt that built this version; its note is the version's publication note.
     *
     * @return HasOne<Publication, $this>
     */
    public function publication(): HasOne
    {
        return $this->hasOne(Publication::class, 'published_version_id');
    }

    /**
     * @return HasMany<SiteVersionRestore, $this>
     */
    public function restores(): HasMany
    {
        return $this->hasMany(SiteVersionRestore::class);
    }

    /**
     * @return HasOne<SiteVersionRestore, $this>
     */
    public function latestRestore(): HasOne
    {
        return $this->hasOne(SiteVersionRestore::class)->latestOfMany();
    }

    /**
     * @return HasMany<PublishedPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(PublishedPage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<PublishedAssetReference, $this>
     */
    public function assetReferences(): HasMany
    {
        return $this->hasMany(PublishedAssetReference::class);
    }
}
