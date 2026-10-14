<?php

namespace App\Marketplace;

use App\Blocks\BlockCatalogAccess;
use App\Enums\MarketplaceProductType;
use App\Models\BlockDefinition;
use App\Models\MarketplaceListing;
use App\Models\Template;

/**
 * Safe Marketplace management props: public IDs and readable labels only. Access mode and prices
 * are read from the canonical product at render time (D-121), never stored on the listing.
 */
final class MarketplaceListingPresenter
{
    public function __construct(private MarketplaceListings $listings) {}

    /**
     * @return array<string, mixed>
     */
    public function listItem(MarketplaceListing $listing): array
    {
        $product = $listing->product();

        return [
            'public_id' => $listing->public_id,
            'title' => $listing->title,
            'slug' => $listing->slug,
            'status' => $listing->status->value,
            'status_label' => $listing->status->label(),
            'product_type' => $listing->product_type->value,
            'product_type_label' => $listing->product_type->label(),
            'product_name' => (string) $product?->name,
            'author' => $this->author($product),
            'access' => $product !== null ? BlockCatalogAccess::card($product) : null,
            'published_at' => $listing->published_at?->toIso8601String(),
        ];
    }

    /**
     * Editor props: marketing text plus the read-only canonical product state.
     *
     * @return array<string, mixed>
     */
    public function detail(MarketplaceListing $listing): array
    {
        $product = $listing->product();
        $denial = $this->listings->publicationDenial($listing);

        return [
            ...$this->listItem($listing),
            'description' => $listing->description,
            'product_slug' => (string) $product?->slug,
            'has_published_version' => $product !== null && $product->versions()->exists(),
            'pricing' => [
                'site_price_minor' => $product?->site_price_minor,
                'workspace_price_minor' => $product?->workspace_price_minor,
                'currency' => $product?->price_currency,
            ],
            'publication_denial' => $denial,
            'publicly_visible' => $listing->isPubliclyVisible(),
        ];
    }

    /**
     * @return array{product_type: string, public_id: string, name: string, has_published_version: bool, access_label: string}
     */
    public function productOption(BlockDefinition|Template $product): array
    {
        return [
            'product_type' => ($product instanceof Template ? MarketplaceProductType::Template : MarketplaceProductType::Block)->value,
            'public_id' => $product->public_id,
            'name' => $product->name,
            'has_published_version' => (int) $product->getAttribute('versions_count') > 0,
            'access_label' => $product->access_mode->label(),
        ];
    }

    private function author(BlockDefinition|Template|null $product): string
    {
        if ($product === null) {
            return '';
        }

        return $product->developer_profile_id === null ? 'Landflow' : (string) $product->developerProfile?->display_name;
    }
}
