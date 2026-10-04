<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoModificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модификация: technical version. Decimal values stay strings (never floats).
 *
 * @property int $series_id
 * @property string $name
 * @property int|null $engine_volume
 * @property string|null $engine_power
 * @property string|null $engine
 * @property string|null $transmission
 * @property string|null $drive
 * @property string|null $consumption_100_km
 * @property string|null $acceleration_0_100
 * @property bool $status
 * @property int $sort_order
 * @property-read AutoSeries $series
 */
#[Fillable([
    'name', 'engine_volume', 'engine_power', 'engine', 'transmission', 'drive',
    'consumption_100_km', 'acceleration_0_100', 'status', 'sort_order',
])]
#[Hidden(['id', 'series_id'])]
class AutoModification extends CatalogModel
{
    /** @use HasFactory<AutoModificationFactory> */
    use HasFactory;

    protected const PARENT_KEY = 'series_id';

    protected $table = 'auto_modifications';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'sort_order' => 'integer',
            'engine_volume' => 'integer',
            'engine_power' => 'decimal:2',
            'consumption_100_km' => 'decimal:3',
            'acceleration_0_100' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AutoSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(AutoSeries::class, 'series_id');
    }

    /**
     * @return HasMany<AutoEquipment, $this>
     */
    public function equipments(): HasMany
    {
        return $this->hasMany(AutoEquipment::class, 'modification_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), true)
            ->whereHas('series', fn (Builder $series) => $series->available());
    }
}
