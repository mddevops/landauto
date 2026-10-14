<?php

namespace App\Models;

use App\Enums\BlockOwnerScope;
use App\Enums\CatalogAccessMode;
use App\Enums\DeveloperProfileStatus;
use App\Enums\MarketplaceListingStatus;
use App\Enums\MarketplaceProductType;
use App\Enums\TemplateOwnerScope;
use App\Models\Concerns\HasImmutablePublicId;
use Carbon\CarbonImmutable;
use Database\Factories\MarketplaceListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Marketplace marketing / distribution metadata of exactly one canonical product — a Block
 * Definition or a Template (P10-001). The product stays the source of truth for sources, versions,
 * access mode, prices and licenses (D-121); the listing never copies them and never pins a version.
 * Its owner follows the product: the product's Developer Profile, or none for official Landflow
 * products (D-117). Workspace-private Blocks cannot be listed. Product and owner identity and the
 * slug never change. `published_at` is the start of the current publication and is cleared on
 * unpublish. There is no review state (D-120).
 *
 * @property int $id
 * @property string $public_id
 * @property MarketplaceProductType $product_type
 * @property int|null $block_definition_id
 * @property int|null $template_id
 * @property int|null $developer_profile_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property MarketplaceListingStatus $status
 * @property CarbonImmutable|null $published_at
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'slug', 'description'])]
#[Hidden(['id', 'block_definition_id', 'template_id', 'developer_profile_id', 'created_by_user_id', 'updated_by_user_id'])]
class MarketplaceListing extends Model
{
    /** @use HasFactory<MarketplaceListingFactory> */
    use HasFactory, HasImmutablePublicId;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const SLUG_MIN = 3;

    public const SLUG_MAX = 80;

    public const TITLE_MAX = 120;

    public const DESCRIPTION_MAX = 5000;

    private const IMMUTABLE = ['slug', 'product_type', 'block_definition_id', 'template_id', 'developer_profile_id', 'created_by_user_id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => MarketplaceProductType::class,
            'status' => MarketplaceListingStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MarketplaceListing $listing): void {
            $listing->assertConsistent();
        });

        static::updating(function (MarketplaceListing $listing): void {
            foreach (self::IMMUTABLE as $attribute) {
                if ($listing->isDirty($attribute)) {
                    throw new LogicException("Marketplace Listing {$attribute} is immutable.");
                }
            }
        });
    }

    public function isPublished(): bool
    {
        return $this->status === MarketplaceListingStatus::Published;
    }

    public function isPlatformOwned(): bool
    {
        return $this->developer_profile_id === null;
    }

    public function product(): BlockDefinition|Template|null
    {
        return $this->product_type === MarketplaceProductType::Template ? $this->template : $this->blockDefinition;
    }

    /**
     * The single public-visibility predicate for the future storefront (P10-002). Fails closed: a
     * published listing whose product has a published version, is listable (platform / Developer,
     * never Workspace-private), is not private `admin_grant` distribution, and — for Developer
     * listings — whose Developer Profile is active.
     *
     * @param  Builder<static>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $listable = fn (Builder $product): Builder => $product
            ->where('access_mode', '!=', CatalogAccessMode::AdminGrant->value)
            ->whereHas('versions');

        $query->where('status', MarketplaceListingStatus::Published->value)
            ->where(fn (Builder $products) => $products
                ->where(fn (Builder $block) => $block
                    ->where('product_type', MarketplaceProductType::Block->value)
                    ->whereHas('blockDefinition', fn (Builder $definition) => $listable($definition)
                        ->whereIn('owner_scope', [BlockOwnerScope::Platform->value, BlockOwnerScope::Developer->value])))
                ->orWhere(fn (Builder $template) => $template
                    ->where('product_type', MarketplaceProductType::Template->value)
                    ->whereHas('template', $listable)))
            ->where(fn (Builder $owner) => $owner
                ->whereNull('developer_profile_id')
                ->orWhereHas('developerProfile', fn (Builder $profile) => $profile->where('status', DeveloperProfileStatus::Active->value)));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->exists && static::query()->publiclyVisible()->whereKey($this->getKey())->exists();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOwnedByDeveloper(Builder $query, DeveloperProfile $profile): void
    {
        $query->where('developer_profile_id', $profile->id);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopePlatformOwned(Builder $query): void
    {
        $query->whereNull('developer_profile_id');
    }

    /**
     * @return BelongsTo<BlockDefinition, $this>
     */
    public function blockDefinition(): BelongsTo
    {
        return $this->belongsTo(BlockDefinition::class);
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
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
     * @return BelongsTo<User, $this>
     */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    private function assertConsistent(): void
    {
        if (preg_match(self::SLUG_PATTERN, $this->slug) !== 1
            || strlen($this->slug) < self::SLUG_MIN || strlen($this->slug) > self::SLUG_MAX) {
            throw new LogicException('Marketplace Listing slug is invalid.');
        }

        $product = match ($this->getAttribute('product_type')) {
            MarketplaceProductType::Block => $this->template_id === null && $this->block_definition_id !== null
                ? BlockDefinition::query()->whereKey($this->block_definition_id)->first()
                : null,
            MarketplaceProductType::Template => $this->block_definition_id === null && $this->template_id !== null
                ? Template::query()->whereKey($this->template_id)->first()
                : null,
            default => null,
        };

        if ($product === null) {
            throw new LogicException('A Marketplace Listing references exactly one existing product matching its product type.');
        }

        $owner = match (true) {
            $product instanceof BlockDefinition => match ($product->owner_scope) {
                BlockOwnerScope::Platform => ['platform', null],
                BlockOwnerScope::Developer => ['developer', $product->developer_profile_id],
                BlockOwnerScope::WorkspacePrivate => null,
            },
            default => match ($product->owner_scope) {
                TemplateOwnerScope::Platform => ['platform', null],
                TemplateOwnerScope::Developer => ['developer', $product->developer_profile_id],
            },
        };

        if ($owner === null) {
            throw new LogicException('Workspace-private Blocks cannot have a Marketplace Listing.');
        }

        if ($owner[1] !== $this->developer_profile_id) {
            throw new LogicException('Marketplace Listing owner must match the owner of its product.');
        }

        $status = $this->getAttribute('status');

        if (! $status instanceof MarketplaceListingStatus || ($status === MarketplaceListingStatus::Published) !== ($this->published_at !== null)) {
            throw new LogicException('Marketplace Listing published_at is set exactly while it is published.');
        }

        if ($status === MarketplaceListingStatus::Published && ($this->isDirty('status') || ! $this->exists)
            && ($product->access_mode === CatalogAccessMode::AdminGrant || ! $product->versions()->exists())) {
            throw new LogicException('Only a product with a published version and public access can be listed publicly.');
        }
    }
}
