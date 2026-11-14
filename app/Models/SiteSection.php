<?php

namespace App\Models;

use App\Enums\SiteSectionType;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property SiteSectionType $type
 * @property string $title
 * @property string $prefix
 * @property bool $enabled
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'title', 'prefix', 'enabled', 'sort_order'])]
#[Hidden(['id', 'site_id'])]
class SiteSection extends Model
{
    /** @use HasFactory<SiteSectionFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SiteSectionType::class,
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SiteSection $section): void {
            if ($section->isDirty('site_id') || $section->isDirty('type')) {
                throw new LogicException('Site section owner and type are immutable.');
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

    /**
     * Generated template Pages (ADR-011 §3).
     *
     * @return HasMany<Page, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /**
     * @return HasMany<SiteSectionRedirect, $this>
     */
    public function redirects(): HasMany
    {
        return $this->hasMany(SiteSectionRedirect::class);
    }
}
