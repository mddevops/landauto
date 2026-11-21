<?php

namespace App\Stock;

use App\Enums\StockVehicleStatus;
use App\Models\SiteStockListing;
use App\Models\VehiclePhoto;
use App\Support\Money;

/**
 * Browser-safe view of a Stock vehicle placed on one Site. Numeric ids stay here.
 */
final class SiteStockPresenter
{
    public function __construct(private StockPresenter $stock) {}

    /**
     * @return array<string, mixed>
     */
    public function card(SiteStockListing $listing): array
    {
        $vehicle = $listing->stockVehicle;
        $source = $this->stock->card($vehicle);
        $priceMinor = $listing->price_minor ?? $vehicle->price_minor;
        $currency = $listing->price_minor === null ? $vehicle->currency : ($listing->currency ?? $vehicle->currency);
        $cover = $listing->photos_custom
            ? ($listing->relationLoaded('photos') ? $listing->photos->first() : $listing->photos()->first())
            : null;

        return [
            'public_id' => $listing->public_id,
            'title' => $source['title'],
            'year' => $vehicle->year,
            'condition_label' => $source['condition_label'],
            'status_label' => $source['status_label'],
            'visible' => $vehicle->status === StockVehicleStatus::Ready,
            'stock_number' => $source['stock_number'],
            'inner_id' => $source['inner_id'],
            'price' => $priceMinor === null ? null : Money::format($priceMinor, $currency),
            'price_from_stock' => $listing->price_minor === null,
            'photos_custom' => $listing->photos_custom,
            'cover_url' => $listing->photos_custom ? $cover?->url() : $source['cover_url'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(SiteStockListing $listing): array
    {
        $vehicle = $listing->stockVehicle;
        $photos = $listing->photos_custom ? $listing->photos : $vehicle->photos;

        return [
            ...$this->card($listing),
            'color_name' => $vehicle->color_name,
            'mileage_km' => $vehicle->mileage_km,
            'availability_label' => $vehicle->availability->label(),
            'stock_price' => $vehicle->price_minor === null ? null : Money::format($vehicle->price_minor, $vehicle->currency),
            'price_input' => $listing->price_minor === null ? '' : Money::toInput($listing->price_minor, $listing->currency ?? $vehicle->currency),
            'photos' => $photos->map(fn (VehiclePhoto $photo): array => [
                'public_id' => $photo->public_id,
                'url' => $photo->url(),
            ])->values()->all(),
        ];
    }
}
