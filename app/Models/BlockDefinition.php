<?php

namespace App\Models;

use App\Enums\BlockCategory;
use App\Enums\BlockOwnerScope;
use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\BlockDefinitionFactory;
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
 * Reusable Block type with explicit ownership (D-117): `platform` (official Landflow), `developer`
 * (one Developer Profile) or `workspace_private` (one Workspace). Ownership and the technical slug
 * never change after creation; creator / editor Users are audit identity only.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $slug
 * @property BlockCategory $category
 * @property BlockOwnerScope $owner_scope
 * @property CatalogAccessMode $access_mode
 * @property Entitlement|null $access_entitlement
 * @property int|null $site_price_minor
 * @property int|null $workspace_price_minor
 * @property string|null $price_currency
 * @property int|null $developer_profile_id
 * @property int|null $workspace_id
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'category'])]
#[Hidden(['id', 'developer_profile_id', 'workspace_id', 'created_by_user_id', 'updated_by_user_id'])]
class BlockDefinition extends Model
{
    /** @use HasFactory<BlockDefinitionFactory> */
    use HasFactory, HasImmutablePublicId;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const SLUG_MIN = 3;

    public const SLUG_MAX = 60;

    public const NAME_MAX = 100;

    private const IMMUTABLE = ['slug', 'owner_scope', 'developer_profile_id', 'workspace_id', 'created_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_scope' => BlockOwnerScope::class,
            'category' => BlockCategory::class,
            'access_mode' => CatalogAccessMode::class,
            'access_entitlement' => Entitlement::class,
            'site_price_minor' => 'integer',
            'workspace_price_minor' => 'integer',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['category' => 'other', 'access_mode' => CatalogAccessMode::Free->value];

    protected static function booted(): void
    {
        static::saving(function (BlockDefinition $definition): void {
            $definition->assertOwnershipIsConsistent();
            $definition->assertAccessIsConsistent();
        });

        static::updating(function (BlockDefinition $definition): void {
            foreach (self::IMMUTABLE as $attribute) {
                if ($definition->isDirty($attribute)) {
                    throw new LogicException("Block Definition {$attribute} is immutable.");
                }
            }
        });
    }

    public function isPlatformOwned(): bool
    {
        return $this->owner_scope === BlockOwnerScope::Platform;
    }

    public function isDeveloperOwned(): bool
    {
        return $this->owner_scope === BlockOwnerScope::Developer;
    }

    public function isWorkspacePrivate(): bool
    {
        return $this->owner_scope === BlockOwnerScope::WorkspacePrivate;
    }

    /**
     * Official Landflow Blocks; customers only ever use those that have a version.
     *
     * @param  Builder<static>  $query
     */
    public function scopePlatformOwned(Builder $query): void
    {
        $query->where('owner_scope', BlockOwnerScope::Platform->value);
    }

    /**
     * Definitions customers may find in the catalog: platform and Developer Blocks. Workspace-private
     * Blocks have no customer runtime yet.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInCatalog(Builder $query): void
    {
        $query->whereIn('owner_scope', [BlockOwnerScope::Platform->value, BlockOwnerScope::Developer->value]);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOwnedByDeveloper(Builder $query, DeveloperProfile $profile): void
    {
        $query->where('owner_scope', BlockOwnerScope::Developer->value)
            ->where('developer_profile_id', $profile->id);
    }

    /**
     * @return HasMany<BlockVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(BlockVersion::class);
    }

    /**
     * @return HasOne<BlockDraft, $this>
     */
    public function draft(): HasOne
    {
        return $this->hasOne(BlockDraft::class);
    }

    /**
     * @return BelongsTo<DeveloperProfile, $this>
     */
    public function developerProfile(): BelongsTo
    {
        return $this->belongsTo(DeveloperProfile::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<CatalogLicense, $this>
     */
    public function catalogLicenses(): HasMany
    {
        return $this->hasMany(CatalogLicense::class);
    }

    private function assertAccessIsConsistent(): void
    {
        if (! CatalogAccessMode::fieldsMatch($this->getAttribute('access_mode'), $this->getAttribute('access_entitlement'), $this->site_price_minor, $this->workspace_price_minor, $this->price_currency)) {
            throw new LogicException('Block Definition access fields must match its access mode exactly.');
        }
    }

    private function assertOwnershipIsConsistent(): void
    {
        $scope = $this->getAttribute('owner_scope');
        $developer = $this->developer_profile_id !== null;
        $workspace = $this->workspace_id !== null;

        $valid = match ($scope instanceof BlockOwnerScope ? $scope : null) {
            BlockOwnerScope::Platform => ! $developer && ! $workspace,
            BlockOwnerScope::Developer => $developer && ! $workspace,
            BlockOwnerScope::WorkspacePrivate => $workspace && ! $developer,
            null => false,
        };

        if (! $valid) {
            throw new LogicException('Block Definition ownership must match its owner scope exactly.');
        }
    }
}
