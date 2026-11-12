<?php

namespace App\Models;

use App\Catalog\AutoCatalog;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Default trim of a customer Catalog vehicle with optional prices (ADR-010 §7): `price_minor` is
 * the price with discount, `rrp_minor` without it. Copied into Site Offers, never synced.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_vehicle_id
 * @property string $catalog_equipment_ref
 * @property int|null $price_minor
 * @property int|null $rrp_minor
 * @property string $currency
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WorkspaceVehicle $vehicle
 */
#[Fillable(['catalog_equipment_ref', 'price_minor', 'rrp_minor', 'currency', 'sort_order'])]
#[Hidden(['id', 'workspace_vehicle_id'])]
class WorkspaceVehicleOffer extends Model
{
    use HasImmutablePublicId;

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
        return ['price_minor' => 'integer', 'rrp_minor' => 'integer', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkspaceVehicleOffer $offer): void {
            if ($offer->exists && $offer->isDirty(['workspace_vehicle_id', 'catalog_equipment_ref'])) {
                throw new LogicException('Workspace vehicle offer vehicle and Equipment are immutable.');
            }

            foreach ([$offer->price_minor, $offer->rrp_minor] as $amount) {
                if ($amount !== null && ($amount < 0 || $amount > Money::MAX_MINOR)) {
                    throw new InvalidCatalogDataException('Offer amount is out of range.');
                }
            }

            if (! $offer->exists) {
                $catalog = app(AutoCatalog::class);
                $series = $catalog->series($offer->vehicle->catalog_series_ref);

                if ($series === null || $catalog->equipment($series, $offer->catalog_equipment_ref) === null) {
                    throw new InvalidCatalogDataException('Offer Equipment must belong to the vehicle Series.');
                }
            }
        });
    }

    /**
     * @return BelongsTo<WorkspaceVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(WorkspaceVehicle::class, 'workspace_vehicle_id');
    }
}
