<?php

namespace App\Models\Catalog;

use Database\Factories\Catalog\AutoEquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Комплектация: the confirmed real Modification + trim combination (no AutoConfiguration).
 *
 * @property int $modification_id
 * @property string $name
 * @property bool $status
 * @property int $sort_order
 * @property-read AutoModification $modification
 */
#[Fillable(['name', 'status', 'sort_order'])]
#[Hidden(['id', 'modification_id'])]
class AutoEquipment extends CatalogModel
{
    /** @use HasFactory<AutoEquipmentFactory> */
    use HasFactory;

    protected const PARENT_KEY = 'modification_id';

    protected $table = 'auto_equipments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<AutoModification, $this>
     */
    public function modification(): BelongsTo
    {
        return $this->belongsTo(AutoModification::class, 'modification_id');
    }

    /**
     * @return HasMany<AutoCharacteristicValue, $this>
     */
    public function characteristicValues(): HasMany
    {
        return $this->hasMany(AutoCharacteristicValue::class, 'equipment_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), true)
            ->whereHas('modification', fn (Builder $modification) => $modification->available());
    }
}
