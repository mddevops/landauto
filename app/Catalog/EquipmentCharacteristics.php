<?php

namespace App\Catalog;

use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoCharacteristicValue;
use App\Models\Catalog\AutoEquipment;
use Illuminate\Support\Facades\DB;

/**
 * Writes Equipment characteristic values (ADR-005): blank input removes the row, numbers are
 * stored canonically (dot decimal separator, no thousands separators).
 */
final class EquipmentCharacteristics
{
    /**
     * @param  array<string, string|null>  $values  characteristic public_id => value
     */
    public function sync(AutoEquipment $equipment, array $values): void
    {
        $parameters = AutoCharacteristic::query()
            ->whereIn('public_id', array_keys($values))
            ->whereNotNull('parent_id')
            ->get()
            ->keyBy('public_id');

        if ($parameters->count() !== count($values)) {
            throw new InvalidCatalogDataException('Unknown characteristic parameter.');
        }

        DB::connection(CatalogDatabase::CONNECTION)->transaction(function () use ($equipment, $values, $parameters): void {
            foreach ($values as $publicId => $raw) {
                /** @var AutoCharacteristic $parameter */
                $parameter = $parameters[$publicId];
                $value = self::canonical($raw);
                $query = AutoCharacteristicValue::query()
                    ->where('equipment_id', $equipment->id)
                    ->where('characteristic_id', $parameter->id);

                if ($value === null) {
                    $query->delete();

                    continue;
                }

                $row = $query->first() ?? new AutoCharacteristicValue;
                $row->equipment_id = $equipment->id;
                $row->characteristic_id = $parameter->id;
                $row->value = $value;
                $row->save();
            }
        });
    }

    public static function canonical(?string $raw): ?string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', (string) $raw));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d{1,3}(?:[ \x{00A0}]\d{3})+(?:[.,]\d+)?$/u', $value) === 1 || preg_match('/^-?\d+,\d+$/', $value) === 1) {
            return str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value);
        }

        return $value;
    }
}
