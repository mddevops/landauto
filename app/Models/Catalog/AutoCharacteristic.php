<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoCharacteristicFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Characteristic section (root) or parameter (child), ordered by `sort`.
 *
 * @property string $name
 * @property int|null $parent_id
 * @property int $sort
 * @property-read AutoCharacteristic|null $parent
 */
class AutoCharacteristic extends CatalogModel
{
    /** @use HasFactory<AutoCharacteristicFactory> */
    use HasFactory;

    protected $table = 'auto_characteristics';

    protected $hidden = ['id', 'parent_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoCharacteristic, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AutoCharacteristic::class, 'parent_id');
    }
}
