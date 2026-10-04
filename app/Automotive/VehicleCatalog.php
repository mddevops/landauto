<?php

namespace App\Automotive;

use App\Enums\Catalog\DriveType;
use App\Enums\Catalog\EngineType;
use App\Enums\Catalog\TransmissionType;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use Illuminate\Support\Collection;

/**
 * Read-only catalog lookups for customer vehicle screens. Catalog rows are resolved by public_id
 * in bulk; numeric catalog keys never leave this class.
 */
final class VehicleCatalog
{
    /**
     * @param  array<int, string>  $publicIds
     * @return Collection<string, AutoSeries>
     */
    public function series(array $publicIds): Collection
    {
        return AutoSeries::query()
            ->with('generation.model.mark')
            ->whereIn('public_id', $publicIds)
            ->get()
            ->keyBy('public_id');
    }

    /**
     * @param  array<int, string>  $publicIds
     * @return Collection<string, AutoEquipment>
     */
    public function equipments(array $publicIds): Collection
    {
        return AutoEquipment::query()
            ->with('modification')
            ->whereIn('public_id', $publicIds)
            ->get()
            ->keyBy('public_id');
    }

    /**
     * Short Russian technical summary, for example "1,6 л · 123 л.с. · Бензин · Автомат · Передний".
     */
    public function modificationSummary(AutoModification $modification): string
    {
        $parts = [];

        if ($modification->engine_volume !== null) {
            $tenths = intdiv($modification->engine_volume + 50, 100);
            $parts[] = intdiv($tenths, 10).','.($tenths % 10).' л';
        }

        if ($modification->engine_power !== null) {
            $parts[] = self::decimal($modification->engine_power).' л.с.';
        }

        $parts[] = EngineType::tryFrom((string) $modification->engine)?->label();
        $parts[] = TransmissionType::tryFrom((string) $modification->transmission)?->label();
        $parts[] = DriveType::tryFrom((string) $modification->drive)?->label();

        return implode(' · ', array_filter($parts));
    }

    /**
     * Decimal catalog string without trailing zeros and with a Russian comma ("123.50" → "123,5").
     */
    public static function decimal(string $value): string
    {
        return str_replace('.', ',', str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value);
    }

    /**
     * @return array{mark: string, model: string, generation: string, series: string, title: string}
     */
    public function seriesTitle(AutoSeries $series): array
    {
        $generation = $series->generation;
        $model = $generation->model;
        $mark = $model->mark->name;

        return [
            'mark' => $mark,
            'model' => $model->name,
            'generation' => $generation->name,
            'series' => $series->name,
            'title' => "{$mark} {$model->name}",
        ];
    }
}
