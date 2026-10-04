<?php

namespace App\Models\Catalog;

use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\Concerns\TwoLevelDictionary;
use Database\Factories\Catalog\AutoCharacteristicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Characteristic group (root) or parameter (child). The unit lives on the parameter definition.
 *
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property string|null $unit
 * @property int $sort_order
 * @property-read AutoCharacteristic|null $parent
 */
#[Fillable(['code', 'name', 'unit', 'sort_order'])]
#[Hidden(['id', 'parent_id'])]
class AutoCharacteristic extends CatalogModel
{
    /** @use HasFactory<AutoCharacteristicFactory> */
    use HasFactory, TwoLevelDictionary;

    protected const PARENT_KEY = 'parent_id';

    /**
     * Modification/Mark/Model fields that must not be duplicated as editable characteristics (ADR-005).
     */
    public const RESERVED_CODES = ['engine_volume', 'engine_power', 'consumption_100_km', 'acceleration_0_100', 'country', 'class'];

    protected $table = 'auto_characteristics';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (AutoCharacteristic $characteristic): void {
            if (in_array($characteristic->code, self::RESERVED_CODES, true)) {
                throw new InvalidCatalogDataException('This code is a Modification/Mark/Model field and cannot be a characteristic.');
            }

            if ($characteristic->parent_id === null && $characteristic->unit !== null) {
                throw new InvalidCatalogDataException('A characteristic group has no unit.');
            }
        });
    }

    /**
     * @return BelongsTo<AutoCharacteristic, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AutoCharacteristic::class, 'parent_id');
    }

    /**
     * @return HasMany<AutoCharacteristic, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(AutoCharacteristic::class, 'parent_id');
    }
}
