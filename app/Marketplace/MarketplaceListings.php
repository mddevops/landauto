<?php

namespace App\Marketplace;

use App\Enums\CatalogAccessMode;
use App\Enums\MarketplaceListingStatus;
use App\Enums\MarketplaceProductType;
use App\Models\BlockDefinition;
use App\Models\DeveloperProfile;
use App\Models\MarketplaceListing;
use App\Models\Template;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Marketplace Listing lifecycle (P10-001): create (Draft) → publish ⇄ unpublish. The product and its
 * owner are resolved on the server from the actor's own scope — official Landflow products for
 * `manage_platform_content`, or the actor's own Developer Profile products — so a listing never
 * reaches another author's or a Workspace-private product. Publication is the author's action after
 * deterministic checks; there is no review (D-120).
 */
final class MarketplaceListings
{
    public function __construct(private MarketplaceListingAuthorization $authorization) {}

    public function createForPlatform(User $actor, MarketplaceProductType $type, string $productPublicId, string $title, string $slug, ?string $description): MarketplaceListing
    {
        $this->authorize($this->authorization->canManagePlatformListings($actor));

        return $this->create($actor, $type, $this->findProduct(null, $type, $productPublicId), $title, $slug, $description);
    }

    public function createForDeveloper(User $actor, MarketplaceProductType $type, string $productPublicId, string $title, string $slug, ?string $description): MarketplaceListing
    {
        $profile = $this->authorization->developerAuthor($actor);
        $this->authorize($profile !== null);

        return $this->create($actor, $type, $this->findProduct($profile, $type, $productPublicId), $title, $slug, $description);
    }

    public function update(User $actor, MarketplaceListing $listing, string $title, ?string $description): void
    {
        $this->authorize($this->authorization->canManage($actor, $listing));

        $listing->title = $title;
        $listing->description = $description;

        if (! $listing->isDirty()) {
            return;
        }

        $listing->lastEditor()->associate($actor);
        $listing->save();
        $this->log('marketplace_listing_updated', $actor, $listing);
    }

    public function publish(User $actor, MarketplaceListing $listing): void
    {
        $this->authorize($this->authorization->canManage($actor, $listing));

        if ($listing->isPublished()) {
            return;
        }

        $denial = $this->publicationDenial($listing);

        if ($denial !== null) {
            throw ValidationException::withMessages(['listing' => $denial]);
        }

        $listing->status = MarketplaceListingStatus::Published;
        $listing->published_at = now();
        $listing->lastEditor()->associate($actor);
        $listing->save();
        $this->log('marketplace_listing_published', $actor, $listing);
    }

    public function unpublish(User $actor, MarketplaceListing $listing): void
    {
        $this->authorize($this->authorization->canManage($actor, $listing));

        if (! $listing->isPublished()) {
            return;
        }

        $listing->status = MarketplaceListingStatus::Draft;
        $listing->published_at = null;
        $listing->lastEditor()->associate($actor);
        $listing->save();
        $this->log('marketplace_listing_unpublished', $actor, $listing);
    }

    /**
     * Why the listing cannot be (or, once published, is not) publicly visible; null when it can.
     * Mirrors `MarketplaceListing::publiclyVisible()`.
     */
    public function publicationDenial(MarketplaceListing $listing): ?string
    {
        $product = $listing->product();
        $template = $listing->product_type === MarketplaceProductType::Template;

        if ($product === null) {
            return 'Продукт карточки не найден.';
        }

        if (! $product->versions()->exists()) {
            return $template
                ? 'Сначала опубликуйте версию шаблона: черновик нельзя показать в Marketplace.'
                : 'Сначала опубликуйте версию блока: черновик нельзя показать в Marketplace.';
        }

        if ($product->access_mode === CatalogAccessMode::AdminGrant) {
            return 'Доступ «Выдаёт администратор» — это частное распространение по лицензиям, такой продукт не публикуется в Marketplace.';
        }

        if (! $listing->isPlatformOwned() && $listing->developerProfile?->isActive() !== true) {
            return 'Профиль разработчика приостановлен.';
        }

        return null;
    }

    /**
     * Own products of the scope (platform when `$owner` is null) that have no listing yet.
     *
     * @return list<BlockDefinition|Template>
     */
    public function unlistedProducts(?DeveloperProfile $owner): array
    {
        return [
            ...$this->blocks($owner)->whereDoesntHave('marketplaceListing')->withCount('versions')->orderBy('name')->orderBy('id')->get()->all(),
            ...$this->templates($owner)->whereDoesntHave('marketplaceListing')->withCount('versions')->orderBy('name')->orderBy('id')->get()->all(),
        ];
    }

    private function findProduct(?DeveloperProfile $owner, MarketplaceProductType $type, string $publicId): BlockDefinition|Template|null
    {
        return match ($type) {
            MarketplaceProductType::Block => $this->blocks($owner)->where('public_id', $publicId)->first(),
            MarketplaceProductType::Template => $this->templates($owner)->where('public_id', $publicId)->first(),
        };
    }

    /**
     * @return Builder<BlockDefinition>
     */
    private function blocks(?DeveloperProfile $owner): Builder
    {
        $query = BlockDefinition::query();

        return $owner === null ? $query->platformOwned() : $query->ownedByDeveloper($owner);
    }

    /**
     * @return Builder<Template>
     */
    private function templates(?DeveloperProfile $owner): Builder
    {
        $query = Template::query();

        return $owner === null ? $query->platformOwned() : $query->ownedByDeveloper($owner);
    }

    private function create(User $actor, MarketplaceProductType $type, BlockDefinition|Template|null $product, string $title, string $slug, ?string $description): MarketplaceListing
    {
        $template = $type === MarketplaceProductType::Template;
        $duplicate = $template ? 'У этого шаблона уже есть карточка в Marketplace.' : 'У этого блока уже есть карточка в Marketplace.';

        if ($product === null) {
            throw ValidationException::withMessages(['product' => $template ? 'Выберите свой шаблон из списка.' : 'Выберите свой блок из списка.']);
        }

        if ($product->marketplaceListing()->exists()) {
            throw ValidationException::withMessages(['product' => $duplicate]);
        }

        $listing = new MarketplaceListing(['title' => $title, 'slug' => $slug, 'description' => $description]);
        $listing->product_type = $type;
        $product instanceof Template ? $listing->template()->associate($product) : $listing->blockDefinition()->associate($product);
        $listing->developer_profile_id = $product->developer_profile_id;
        $listing->creator()->associate($actor);
        $listing->lastEditor()->associate($actor);

        try {
            $listing->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(MarketplaceListing::query()->where('slug', $slug)->exists()
                ? ['slug' => 'Этот slug уже используется другой карточкой.']
                : ['product' => $duplicate]);
        }

        $this->log('marketplace_listing_created', $actor, $listing);

        return $listing;
    }

    private function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    private function log(string $action, User $actor, MarketplaceListing $listing): void
    {
        $context = [
            'listing' => $listing->public_id,
            'product_type' => $listing->product_type->value,
            $listing->product_type->value => $listing->product()?->public_id,
            'status' => $listing->status->value,
        ];

        if (! $listing->isPlatformOwned()) {
            $context['developer_profile'] = $listing->developerProfile?->public_id;
        }

        $prefix = $listing->isPlatformOwned() ? 'platform' : 'developer';

        Log::info("{$prefix}.{$action}", [...$context, 'actor_user_id' => $actor->id]);
    }
}
