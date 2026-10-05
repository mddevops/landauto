<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property string $title
 * @property string $slug
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property bool $seo_noindex
 * @property int $sort_order
 * @property bool $is_home
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'slug', 'sort_order'])]
#[Hidden(['id', 'site_id'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasImmutablePublicId;

    public const HOME_TITLE = 'Главная';

    public const HOME_SLUG = 'home';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'seo_noindex' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Page $page): void {
            if ($page->isDirty('site_id')) {
                throw new LogicException('Page site is immutable.');
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
     * @return HasMany<BlockInstance, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(BlockInstance::class)->orderBy('sort_order');
    }

    /**
     * Stored as TRUE or NULL so the (site_id, is_home) unique index allows one home Page per Site.
     *
     * @return Attribute<bool, bool>
     */
    protected function isHome(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): bool => (bool) $value,
            set: fn (bool $value): ?bool => $value ? true : null,
        );
    }
}
