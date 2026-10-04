<?php

namespace App\Catalog;

use App\Enums\Catalog\OptionAvailability;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoOption;
use App\Models\Catalog\AutoOptionValue;
use Illuminate\Support\Facades\DB;

/**
 * Writes Equipment option values; "unknown" removes the row instead of guessing is_base.
 */
final class EquipmentOptions
{
    /**
     * @param  array<string, OptionAvailability>  $values  option public_id => availability
     */
    public function sync(AutoEquipment $equipment, array $values): void
    {
        $options = AutoOption::query()
            ->whereIn('public_id', array_keys($values))
            ->whereNotNull('parent_id')
            ->get()
            ->keyBy('public_id');

        if ($options->count() !== count($values)) {
            throw new InvalidCatalogDataException('Unknown option.');
        }

        DB::connection(CatalogDatabase::CONNECTION)->transaction(function () use ($equipment, $values, $options): void {
            foreach ($values as $publicId => $availability) {
                /** @var AutoOption $option */
                $option = $options[$publicId];
                $isBase = $availability->isBase();
                $query = AutoOptionValue::query()
                    ->where('equipment_id', $equipment->id)
                    ->where('option_id', $option->id);

                if ($isBase === null) {
                    $query->delete();

                    continue;
                }

                $row = $query->first() ?? new AutoOptionValue;
                $row->equipment_id = $equipment->id;
                $row->option_id = $option->id;
                $row->is_base = $isBase;
                $row->save();
            }
        });
    }
}
