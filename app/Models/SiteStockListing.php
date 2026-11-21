<?php

namespace App\Models;

use App\Automotive\VehiclePhotos;
use App\Enums\StockCondition;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteStockListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Stock vehicle placed on one Site. A null price and `photos_custom = false` mean the Site
 * shows the Workspace Stock card. Mileage, condition and availability stay on the Stock vehicle.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int $stock_vehicle_id
 * @property int|null $price_minor
 * @property string|null $currency
 * @property bool $photos_custom
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site $site
 * @property-read StockVehicle $stockVehicle
 */
#[Fillable(['price_minor', 'currency', 'photos_custom'])]
#[Hidden(['id', 'site_id', 'stock_vehicle_id'])]
class SiteStockListing extends Model
{
    /** @use HasFactory<SiteStockListingFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'photos_custom' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'photos_custom' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (SiteStockListing $listing): void {
            $listing->photos()->get()->each(function (VehiclePhoto $photo): void {
                app(VehiclePhotos::class)->remove($photo);
            });
        });
    }

    /**
     * Ready Stock of this condition that is explicitly placed on the Site.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleFor(Builder $query, Site $site, StockCondition $condition): void
    {
        $query->where('site_id', $site->id)
            ->whereHas('stockVehicle', fn (Builder $stock) => $stock->ready()->where('condition', $condition))
            ->orderByDesc('id');
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<StockVehicle, $this>
     */
    public function stockVehicle(): BelongsTo
    {
        return $this->belongsTo(StockVehicle::class);
    }

    /**
     * @return HasMany<VehiclePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class, 'site_stock_listing_id')->orderBy('sort_order')->orderBy('id');
    }

    public function photoOwnerColumn(): string
    {
        return 'site_stock_listing_id';
    }

    public function photoWorkspaceId(): int
    {
        if ($this->relationLoaded('site')) {
            return $this->site->workspace_id;
        }

        return (int) Site::query()->whereKey($this->site_id)->value('workspace_id');
    }
}
