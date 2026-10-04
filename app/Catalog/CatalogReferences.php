<?php

namespace App\Catalog;

use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoSeries;

/**
 * Application-level validation of cross-database catalog references (no SQL FK, D-102).
 */
final class CatalogReferences
{
    public function series(?string $publicId, bool $availableOnly = false): ?AutoSeries
    {
        if (! $this->isUlid($publicId)) {
            return null;
        }

        $query = AutoSeries::query()->where('public_id', $publicId);

        return ($availableOnly ? $query->available() : $query)->first();
    }

    public function equipment(?string $publicId, bool $availableOnly = false): ?AutoEquipment
    {
        if (! $this->isUlid($publicId)) {
            return null;
        }

        $query = AutoEquipment::query()->where('public_id', $publicId);

        return ($availableOnly ? $query->available() : $query)->first();
    }

    /**
     * Whether the Equipment chain reaches the given Series.
     */
    public function equipmentBelongsToSeries(AutoEquipment $equipment, string $seriesPublicId): bool
    {
        return $equipment->modification->series->public_id === $seriesPublicId;
    }

    /**
     * @phpstan-assert-if-true string $value
     */
    private function isUlid(?string $value): bool
    {
        return is_string($value) && preg_match('/^[0-9a-hjkmnp-tv-z]{26}$/', $value) === 1;
    }
}
