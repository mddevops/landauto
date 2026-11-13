<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Option of an Equipment: `is_base` = standard, otherwise optional at extra cost.
 *
 * @property int $option_id
 * @property int $equipment_id
 * @property bool $is_base
 * @property-read AutoOption $option
 */
class AutoOptionValue extends CatalogModel
{
    protected $table = 'auto_option_values';

    protected $hidden = ['id', 'option_id', 'equipment_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_base' => 'boolean'];
    }

    /**
     * @return BelongsTo<AutoOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(AutoOption::class, 'option_id');
    }
}
