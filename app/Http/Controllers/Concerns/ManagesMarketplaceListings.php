<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\MarketplaceProductType;
use App\Http\Requests\Marketplace\StoreMarketplaceListingRequest;
use App\Http\Requests\Marketplace\UpdateMarketplaceListingRequest;
use App\Marketplace\MarketplaceListingPresenter;
use App\Marketplace\MarketplaceListings;
use App\Models\BlockDefinition;
use App\Models\DeveloperProfile;
use App\Models\MarketplaceListing;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Marketplace Listing management for one owner scope: official Landflow (`owner()` is null) or the
 * current User's own Developer Profile. A listing outside the scope is indistinguishable from a
 * missing one (404).
 */
trait ManagesMarketplaceListings
{
    /**
     * The Developer Profile whose listings are managed, or null for official Landflow listings.
     */
    abstract protected function owner(Request $request): ?DeveloperProfile;

    abstract protected function routePrefix(): string;

    abstract protected function pagePrefix(): string;

    public function index(Request $request, MarketplaceListings $listings, MarketplaceListingPresenter $presenter): Response
    {
        $owner = $this->owner($request);

        return Inertia::render($this->pagePrefix().'/index', [
            'listings' => $this->scopedListings($owner)
                ->with(['blockDefinition.developerProfile', 'template.developerProfile'])
                ->latest('id')
                ->get()
                ->map(fn (MarketplaceListing $listing): array => $presenter->listItem($listing))
                ->values()
                ->all(),
            'products' => array_map(
                fn (BlockDefinition|Template $product): array => $presenter->productOption($product),
                $listings->unlistedProducts($owner),
            ),
            'productTypes' => MarketplaceProductType::options(),
        ]);
    }

    public function store(StoreMarketplaceListingRequest $request, MarketplaceListings $listings): RedirectResponse
    {
        $actor = $this->marketplaceActor($request);
        $arguments = [$actor, $request->productType(), $request->product(), $request->title(), $request->slug(), $request->description()];
        $listing = $this->owner($request) === null
            ? $listings->createForPlatform(...$arguments)
            : $listings->createForDeveloper(...$arguments);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Карточка «{$listing->title}» создана."]);

        return to_route($this->routePrefix().'.show', $listing->public_id);
    }

    public function show(Request $request, string $listing, MarketplaceListingPresenter $presenter): Response
    {
        return Inertia::render($this->pagePrefix().'/show', [
            'listing' => $presenter->detail($this->findListing($request, $listing)),
        ]);
    }

    public function update(UpdateMarketplaceListingRequest $request, string $listing, MarketplaceListings $listings): RedirectResponse
    {
        $found = $this->findListing($request, $listing);
        $listings->update($this->marketplaceActor($request), $found, $request->title(), $request->description());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изменения сохранены.']);

        return to_route($this->routePrefix().'.show', $found->public_id);
    }

    public function publish(Request $request, string $listing, MarketplaceListings $listings): RedirectResponse
    {
        $found = $this->findListing($request, $listing);
        $listings->publish($this->marketplaceActor($request), $found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Карточка опубликована в Marketplace.']);

        return to_route($this->routePrefix().'.show', $found->public_id);
    }

    public function unpublish(Request $request, string $listing, MarketplaceListings $listings): RedirectResponse
    {
        $found = $this->findListing($request, $listing);
        $listings->unpublish($this->marketplaceActor($request), $found);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Карточка снята с публикации.']);

        return to_route($this->routePrefix().'.show', $found->public_id);
    }

    /**
     * @return Builder<MarketplaceListing>
     */
    private function scopedListings(?DeveloperProfile $owner): Builder
    {
        $query = MarketplaceListing::query();

        return $owner === null ? $query->platformOwned() : $query->ownedByDeveloper($owner);
    }

    private function findListing(Request $request, string $publicId): MarketplaceListing
    {
        return $this->scopedListings($this->owner($request))
            ->where('public_id', $publicId)
            ->with(['blockDefinition.developerProfile', 'template.developerProfile', 'developerProfile'])
            ->firstOrFail();
    }

    private function marketplaceActor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
