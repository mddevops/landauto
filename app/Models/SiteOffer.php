<?php

namespace App\Models;

use App\Catalog\CatalogReferences;
use App\Enums\BenefitType;
use App\Enums\OfferAvailability;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use App\Support\Money;
use Database\Factories\SiteOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Site-owned commercial offer for one catalog Equipment of a SiteVehicle (D-104). Factory data
 * stays in the catalog; the offer owns price, benefits, availability and badge (ADR-004 money).
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_vehicle_id
 * @property string $catalog_equipment_public_id
 * @property int $price_minor
 * @property int|null $rrp_minor
 * @property string $currency
 * @property OfferAvailability|null $availability
 * @property string|null $badge
 * @property bool $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['catalog_equipment_public_id', 'price_minor', 'rrp_minor', 'currency', 'availability', 'badge', 'status', 'sort_order'])]
#[Hidden(['id', 'site_vehicle_id'])]
class SiteOffer extends Model
{
    /** @use HasFactory<SiteOfferFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => Money::DEFAULT_CURRENCY,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'rrp_minor' => 'integer',
            'availability' => OfferAvailability::class,
            'status' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (SiteOffer $offer): void {
            if ($offer->exists && $offer->isDirty('site_vehicle_id')) {
                throw new LogicException('Site offer vehicle is immutable.');
            }

            if (! Money::supports($offer->currency)) {
                throw new InvalidCatalogDataException('Unsupported offer currency.');
            }

            foreach ([$offer->price_minor, $offer->rrp_minor] as $amount) {
                if ($amount !== null && ($amount < 0 || $amount > Money::MAX_MINOR)) {
                    throw new InvalidCatalogDataException('Offer amount is out of range.');
                }
            }

            if (! $offer->exists || $offer->isDirty('catalog_equipment_public_id')) {
                $references = app(CatalogReferences::class);
                $equipment = $references->equipment($offer->catalog_equipment_public_id);

                if ($equipment === null || ! $references->equipmentBelongsToSeries($equipment, $offer->vehicle->catalog_series_public_id)) {
                    throw new InvalidCatalogDataException('Offer Equipment must belong to the vehicle Series.');
                }
            }
        });
    }

    /**
     * @return BelongsTo<SiteVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(SiteVehicle::class, 'site_vehicle_id');
    }

    /**
     * @return HasMany<SiteOfferBenefit, $this>
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(SiteOfferBenefit::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Replace all benefits in the given order.
     *
     * @param  list<array{type: BenefitType, amount_minor: int, label?: string|null}>  $benefits
     */
    public function replaceBenefits(array $benefits): void
    {
        DB::transaction(function () use ($benefits): void {
            $this->benefits()->delete();

            foreach ($benefits as $position => $benefit) {
                $this->benefits()->create([
                    'type' => $benefit['type'],
                    'amount_minor' => $benefit['amount_minor'],
                    'label' => $benefit['label'] ?? null,
                    'sort_order' => $position,
                ]);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
