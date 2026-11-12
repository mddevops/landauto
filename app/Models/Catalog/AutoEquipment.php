<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoEquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Комплектация of one Modification. `external_id = 0` is the modification itself without a
 * named trim («Базовая»). Customer rows reference it by `external_key` inside the Series.
 *
 * @property int $series_id
 * @property int $modification_id
 * @property int $external_id
 * @property string $external_key
 * @property string $name
 * @property-read AutoModification $modification
 * @property-read AutoSeries $series
 */
class AutoEquipment extends CatalogModel
{
    /** @use HasFactory<AutoEquipmentFactory> */
    use HasFactory;

    public const BASE_NAME = 'Базовая';

    protected $table = 'auto_equipments';

    protected $hidden = ['id', 'series_id', 'modification_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['external_id' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoModification, $this>
     */
    public function modification(): BelongsTo
    {
        return $this->belongsTo(AutoModification::class, 'modification_id');
    }

    /**
     * @return BelongsTo<AutoSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(AutoSeries::class, 'series_id');
    }

    /**
     * @return HasMany<AutoOptionValue, $this>
     */
    public function optionValues(): HasMany
    {
        return $this->hasMany(AutoOptionValue::class, 'equipment_id');
    }

    public function isNamed(): bool
    {
        return $this->external_id > 0;
    }

    public function displayName(): string
    {
        return $this->isNamed() ? $this->name : self::BASE_NAME;
    }
}
