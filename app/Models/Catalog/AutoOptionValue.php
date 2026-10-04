<?php

namespace App\Models\Catalog;

use App\Catalog\CatalogDatabase;
use App\Exceptions\InvalidCatalogDataException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * is_base = true: standard equipment; false: available at extra cost. No row: unknown.
 *
 * @property int $id
 * @property int $option_id
 * @property int $equipment_id
 * @property bool $is_base
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AutoOption $option
 */
class AutoOptionValue extends Model
{
    protected $connection = CatalogDatabase::CONNECTION;

    protected $table = 'auto_option_values';

    protected $hidden = ['id', 'option_id', 'equipment_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_base' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (AutoOptionValue $value): void {
            if ($value->getAttribute('is_base') === null) {
                throw new InvalidCatalogDataException('is_base must be set explicitly.');
            }

            if (AutoOption::query()->whereKey($value->option_id)->whereNotNull('parent_id')->doesntExist()) {
                throw new InvalidCatalogDataException('Values can reference options only, not option groups.');
            }
        });
    }

    /**
     * @return BelongsTo<AutoOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(AutoOption::class, 'option_id');
    }

    /**
     * @return BelongsTo<AutoEquipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(AutoEquipment::class, 'equipment_id');
    }
}
