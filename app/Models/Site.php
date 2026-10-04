<?php

namespace App\Models;

use App\Enums\SiteStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property string $name
 * @property SiteStatus $status
 * @property array<string, string>|null $design_tokens
 * @property array<string, int|bool>|null $form_security
 * @property int|null $active_published_version_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'status', 'design_tokens'])]
#[Hidden(['id', 'workspace_id', 'active_published_version_id'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SiteStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'design_tokens' => 'array',
            'form_security' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Site $site): void {
            if ($site->isDirty('workspace_id')) {
                throw new LogicException('Site workspace is immutable outside a dedicated transfer workflow.');
            }

            if ($site->isDirty('active_published_version_id') && $site->active_published_version_id !== null) {
                $version = PublishedVersion::query()->whereKey($site->active_published_version_id)->first();

                if ($version === null || $version->site_id !== $site->id || ! $version->isReady()) {
                    throw new LogicException('The production pointer must reference a ready version of this Site.');
                }
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return HasMany<SiteAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(SiteAsset::class);
    }

    /**
     * @return HasMany<SiteVehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(SiteVehicle::class);
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return HasMany<Form, $this>
     */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /**
     * @return HasMany<Popup, $this>
     */
    public function popups(): HasMany
    {
        return $this->hasMany(Popup::class);
    }

    /**
     * @return HasOne<Page, $this>
     */
    public function homePage(): HasOne
    {
        return $this->hasOne(Page::class)->where('is_home', true);
    }

    /**
     * @return HasMany<PublishedVersion, $this>
     */
    public function publishedVersions(): HasMany
    {
        return $this->hasMany(PublishedVersion::class);
    }

    /**
     * The production pointer; changed only by the atomic Publish activation (ADR-006 §5).
     *
     * @return BelongsTo<PublishedVersion, $this>
     */
    public function activePublishedVersion(): BelongsTo
    {
        return $this->belongsTo(PublishedVersion::class, 'active_published_version_id');
    }
}
