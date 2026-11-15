<?php

namespace App\Models;

use App\Catalog\AutoCatalog;
use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockPtsDocumentType;
use App\Enums\StockVehicleStatus;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use App\Support\Money;
use Database\Factories\StockVehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * Workspace-owned Stock vehicle for sale (ADR-012). Concrete unit, not a Series family page.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property StockCondition $condition
 * @property string $catalog_series_ref
 * @property string|null $catalog_equipment_ref
 * @property int $year
 * @property string|null $body_name
 * @property string $color_name
 * @property string|null $color_swatch_hex
 * @property int|null $mileage_km
 * @property int|null $owners_count
 * @property string|null $vin
 * @property StockAvailability $availability
 * @property int|null $price_minor
 * @property string $currency
 * @property string|null $inner_id
 * @property string $stock_number
 * @property StockVehicleStatus $status
 * @property StockPtsDocumentType|null $pts_document_type
 * @property int|null $purchased_year
 * @property int|null $purchased_month
 * @property bool $on_warranty
 * @property bool $not_customs_cleared
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workspace|null $workspace
 */
#[Fillable([
    'catalog_equipment_ref',
    'year',
    'body_name',
    'color_name',
    'color_swatch_hex',
    'mileage_km',
    'owners_count',
    'vin',
    'availability',
    'price_minor',
    'currency',
    'inner_id',
    'status',
    'pts_document_type',
    'purchased_year',
    'purchased_month',
    'on_warranty',
    'not_customs_cleared',
    'description',
])]
#[Hidden(['id', 'workspace_id'])]
class StockVehicle extends Model
{
    /** @use HasFactory<StockVehicleFactory> */
    use HasFactory, HasImmutablePublicId;

    public const STOCK_NUMBER_LENGTH = 10;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => Money::DEFAULT_CURRENCY,
        'status' => StockVehicleStatus::Draft->value,
        'availability' => StockAvailability::InStock->value,
        'on_warranty' => false,
        'not_customs_cleared' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => StockCondition::class,
            'availability' => StockAvailability::class,
            'status' => StockVehicleStatus::class,
            'pts_document_type' => StockPtsDocumentType::class,
            'year' => 'integer',
            'mileage_km' => 'integer',
            'owners_count' => 'integer',
            'price_minor' => 'integer',
            'purchased_year' => 'integer',
            'purchased_month' => 'integer',
            'on_warranty' => 'boolean',
            'not_customs_cleared' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockVehicle $vehicle): void {
            $number = $vehicle->attributes['stock_number'] ?? '';

            if ($number === '') {
                $vehicle->stock_number = static::allocateStockNumber((int) $vehicle->workspace_id);
            }
        });

        static::saving(function (StockVehicle $vehicle): void {
            if ($vehicle->exists && $vehicle->isDirty(['workspace_id', 'condition', 'stock_number'])) {
                throw new LogicException('Stock vehicle Workspace, condition and stock number are immutable.');
            }

            if ($vehicle->exists && $vehicle->isDirty('catalog_series_ref')) {
                throw new LogicException('Stock vehicle Series is immutable.');
            }

            if (! $vehicle->exists && app(AutoCatalog::class)->series($vehicle->catalog_series_ref) === null) {
                throw new InvalidCatalogDataException('Stock vehicle Series does not exist in the catalog.');
            }

            if ($vehicle->condition === StockCondition::New) {
                $vehicle->mileage_km = null;
                $vehicle->owners_count = null;
                $vehicle->vin = null;
            }

            if ($vehicle->condition === StockCondition::Used && $vehicle->status === StockVehicleStatus::Ready) {
                if ($vehicle->mileage_km === null) {
                    throw new LogicException('Used Stock vehicles require mileage when Ready.');
                }

                if ($vehicle->owners_count === null) {
                    throw new LogicException('Used Stock vehicles require owners count when Ready.');
                }
            }

            if ($vehicle->vin !== null) {
                $vin = strtoupper(preg_replace('/\s+/', '', $vehicle->vin) ?? '');
                $vehicle->vin = $vin === '' ? null : $vin;
            }

            $currency = $vehicle->attributes['currency'] ?? Money::DEFAULT_CURRENCY;

            if ($currency === '') {
                $currency = Money::DEFAULT_CURRENCY;
                $vehicle->currency = $currency;
            }

            if (! Money::supports($currency)) {
                throw new InvalidCatalogDataException('Unsupported Stock currency.');
            }

            if ($vehicle->price_minor !== null && ($vehicle->price_minor < 0 || $vehicle->price_minor > Money::MAX_MINOR)) {
                throw new InvalidCatalogDataException('Stock price is out of range.');
            }

            if ($vehicle->inner_id !== null) {
                $vehicle->inner_id = trim($vehicle->inner_id);
                if ($vehicle->inner_id === '') {
                    $vehicle->inner_id = null;
                }
            }
        });
    }

    /**
     * Public URL last segment for Used / Specials (D-132): dealer `inner_id` when set, otherwise
     * the platform `stock_number`. Never the ULID. Path-safe lowercase `[a-z0-9-]+`.
     */
    public function publicPathKey(): string
    {
        $raw = $this->inner_id !== null && $this->inner_id !== ''
            ? $this->inner_id
            : $this->stock_number;
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $raw), '-'));

        return $slug !== '' ? $slug : $this->stock_number;
    }

    public static function allocateStockNumber(int $workspaceId): string
    {
        do {
            $number = strtolower(Str::random(self::STOCK_NUMBER_LENGTH));
        } while (static::query()
            ->where('workspace_id', $workspaceId)
            ->where('stock_number', $number)
            ->exists());

        return $number;
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function photoOwnerColumn(): string
    {
        return 'stock_vehicle_id';
    }

    public function photoWorkspaceId(): int
    {
        return $this->workspace_id;
    }

    /**
     * @return HasMany<VehiclePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class, 'stock_vehicle_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeReady(Builder $query): void
    {
        $query->where('status', StockVehicleStatus::Ready);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('id');
    }
}
