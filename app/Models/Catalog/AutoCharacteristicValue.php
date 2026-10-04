<?php

namespace App\Models\Catalog;

use App\Catalog\CatalogDatabase;
use App\Exceptions\InvalidCatalogDataException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Value of one characteristic parameter for one Equipment. Missing data is a missing row.
 *
 * @property int $id
 * @property int $equipment_id
 * @property int $characteristic_id
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AutoCharacteristic $characteristic
 */
class AutoCharacteristicValue extends Model
{
    protected $connection = CatalogDatabase::CONNECTION;

    protected $table = 'auto_characteristic_values';

    protected $hidden = ['id', 'equipment_id', 'characteristic_id'];

    protected static function booted(): void
    {
        static::saving(function (AutoCharacteristicValue $value): void {
            if (trim($value->value) === '') {
                throw new InvalidCatalogDataException('An empty characteristic value is stored as a missing row.');
            }

            if (AutoCharacteristic::query()->whereKey($value->characteristic_id)->whereNotNull('parent_id')->doesntExist()) {
                throw new InvalidCatalogDataException('Values can reference characteristic parameters only.');
            }
        });
    }

    /**
     * @return BelongsTo<AutoCharacteristic, $this>
     */
    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(AutoCharacteristic::class, 'characteristic_id');
    }

    /**
     * @return BelongsTo<AutoEquipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(AutoEquipment::class, 'equipment_id');
    }
}
