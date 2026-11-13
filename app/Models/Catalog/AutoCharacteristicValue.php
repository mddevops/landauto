<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Characteristic value of a Modification; `unit` is stored next to the value.
 *
 * @property int $modification_id
 * @property int $characteristic_id
 * @property string|null $value
 * @property string|null $unit
 * @property-read AutoCharacteristic $characteristic
 */
class AutoCharacteristicValue extends CatalogModel
{
    protected $table = 'auto_characteristic_values';

    protected $hidden = ['id', 'modification_id', 'characteristic_id'];

    /**
     * @return BelongsTo<AutoCharacteristic, $this>
     */
    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(AutoCharacteristic::class, 'characteristic_id');
    }
}
