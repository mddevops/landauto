<?php

namespace App\Models;

use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Enums\SiteType;
use App\Enums\TemplateOwnerScope;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Site start structure with explicit ownership (P9-007): `platform` (official Landflow) or
 * `developer` (one Developer Profile). Draft content lives in Template Pages / Template Blocks;
 * publishing snapshots it into an immutable Template Version. A Template never owns customer
 * vehicles, prices, integrations, domains or Site commercial state.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property TemplateOwnerScope $owner_scope
 * @property int|null $developer_profile_id
 * @property list<string>|null $site_types
 * @property CatalogAccessMode $access_mode
 * @property Entitlement|null $access_entitlement
 * @property int|null $price_minor
 * @property string|null $price_currency
 * @property bool $is_official
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug'])]
#[Hidden(['id', 'developer_profile_id', 'created_by_user_id', 'updated_by_user_id'])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory, HasImmutablePublicId;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const NAME_MAX = 100;

    private const IMMUTABLE = ['slug', 'owner_scope', 'developer_profile_id', 'created_by_user_id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['owner_scope' => 'platform', 'access_mode' => CatalogAccessMode::Free->value];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_scope' => TemplateOwnerScope::class,
            'is_official' => 'boolean',
            'site_types' => 'array',
            'access_mode' => CatalogAccessMode::class,
            'access_entitlement' => Entitlement::class,
            'price_minor' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Template $template): void {
            $scope = $template->getAttribute('owner_scope');
            $developer = $template->developer_profile_id !== null;

            if (! ($scope === TemplateOwnerScope::Platform && ! $developer) && ! ($scope === TemplateOwnerScope::Developer && $developer)) {
                throw new LogicException('Template ownership must match its owner scope exactly.');
            }

            if (! CatalogAccessMode::fieldsMatch($template->getAttribute('access_mode'), $template->getAttribute('access_entitlement'), $template->price_minor, $template->price_currency)) {
                throw new LogicException('Template access fields must match its access mode exactly.');
            }
        });

        static::updating(function (Template $template): void {
            foreach (self::IMMUTABLE as $attribute) {
                if ($template->isDirty($attribute)) {
                    throw new LogicException("Template {$attribute} is immutable.");
                }
            }
        });
    }

    /**
     * Compatible Site types declared by the Template (D-119), in canonical order.
     *
     * @return list<SiteType>
     */
    public function siteTypes(): array
    {
        $stored = $this->site_types ?? [];

        return array_values(array_filter(SiteType::cases(), fn (SiteType $type): bool => in_array($type->value, $stored, true)));
    }

    public function supports(SiteType $type): bool
    {
        return in_array($type, $this->siteTypes(), true);
    }

    public function isPlatformOwned(): bool
    {
        return $this->owner_scope === TemplateOwnerScope::Platform;
    }

    /**
     * Customer catalog (P9-015): platform and Developer Templates with a published Template Version.
     * Compatibility and access are checked per Site type and Workspace on installation.
     *
     * @param  Builder<Template>  $query
     */
    public function scopeAvailableForSites(Builder $query): void
    {
        $query->whereHas('versions');
    }

    /**
     * @param  Builder<Template>  $query
     */
    public function scopePlatformOwned(Builder $query): void
    {
        $query->where('owner_scope', TemplateOwnerScope::Platform->value);
    }

    /**
     * @param  Builder<Template>  $query
     */
    public function scopeOwnedByDeveloper(Builder $query, DeveloperProfile $profile): void
    {
        $query->where('owner_scope', TemplateOwnerScope::Developer->value)
            ->where('developer_profile_id', $profile->id);
    }

    /**
     * @return HasMany<TemplateVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }

    /**
     * @return HasOne<TemplateVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class)->latestOfMany();
    }

    /**
     * Draft Pages, home first.
     *
     * @return HasMany<TemplatePage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(TemplatePage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<DeveloperProfile, $this>
     */
    public function developerProfile(): BelongsTo
    {
        return $this->belongsTo(DeveloperProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<SiteLicense, $this>
     */
    public function siteLicenses(): HasMany
    {
        return $this->hasMany(SiteLicense::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
