<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoModificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модификация (engine + gearbox + drive) of a Series.
 *
 * @property int $series_id
 * @property int $external_id
 * @property string $name
 * @property string|null $engine_volume
 * @property int|null $engine_power
 * @property string|null $engine
 * @property string|null $transmission
 * @property string|null $drive
 * @property string|null $consumption_100_km
 * @property string|null $consumption_city
 * @property string|null $consumption_highway
 * @property string|null $acceleration_0_100
 * @property-read AutoSeries $series
 */
class AutoModification extends CatalogModel
{
    /** @use HasFactory<AutoModificationFactory> */
    use HasFactory;

    protected $table = 'auto_modifications';

    protected $hidden = ['id', 'series_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['external_id' => 'integer', 'engine_power' => 'integer'];
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
     * @return HasMany<AutoCharacteristicValue, $this>
     */
    public function characteristicValues(): HasMany
    {
        return $this->hasMany(AutoCharacteristicValue::class, 'modification_id');
    }
}
