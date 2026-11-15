<?php

namespace App\Stock;

use App\Catalog\AutoCatalog;
use App\Catalog\ModificationFacts;
use App\Enums\StockCondition;
use App\Models\Catalog\AutoEquipment;
use App\Models\StockVehicle;
use App\Models\VehiclePhoto;
use App\Support\Money;

/**
 * Browser-safe Stock view models (ADR-012). No numeric IDs leave this class.
 */
final class StockPresenter
{
    public function __construct(private AutoCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function card(StockVehicle $vehicle): array
    {
        $series = $this->catalog->series($vehicle->catalog_series_ref);
        $names = $series === null ? null : $this->catalog->title($series);
        $cover = $vehicle->relationLoaded('photos')
            ? $vehicle->photos->first()
            : $vehicle->photos()->orderBy('sort_order')->orderBy('id')->first();

        return [
            'public_id' => $vehicle->public_id,
            'title' => $names['title'] ?? 'Автомобиль',
            'mark' => $names['mark'] ?? null,
            'model' => $names['model'] ?? null,
            'body_name' => $vehicle->body_name,
            'stock_number' => $vehicle->stock_number,
            'inner_id' => $vehicle->inner_id,
            'path_key' => $vehicle->publicPathKey(),
            'condition' => $vehicle->condition->value,
            'condition_label' => $vehicle->condition->label(),
            'status' => $vehicle->status->value,
            'status_label' => $vehicle->status->label(),
            'year' => $vehicle->year,
            'color_name' => $vehicle->color_name,
            'color_swatch_hex' => $vehicle->color_swatch_hex,
            'mileage_km' => $vehicle->mileage_km,
            'owners_count' => $vehicle->owners_count,
            'availability' => $vehicle->availability->value,
            'availability_label' => $vehicle->availability->label(),
            'price' => $vehicle->price_minor === null ? null : Money::format($vehicle->price_minor, $vehicle->currency),
            'photos_count' => $vehicle->relationLoaded('photos')
                ? $vehicle->photos->count()
                : $vehicle->photos()->count(),
            'cover_url' => $cover?->url(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(StockVehicle $vehicle): array
    {
        $series = $this->catalog->series($vehicle->catalog_series_ref);
        $names = $series === null ? null : $this->catalog->title($series);
        $facts = $this->modificationFacts($vehicle);
        $equipment = $this->resolveEquipment($vehicle);

        return [
            ...$this->card($vehicle),
            'series_ref' => $vehicle->catalog_series_ref,
            'equipment_ref' => $vehicle->catalog_equipment_ref,
            'generation' => $names['generation'] ?? null,
            'generation_years' => $names['years'] ?? null,
            'series_name' => $names['series'] ?? null,
            'modification_label' => $facts?->label(),
            'price_input' => $vehicle->price_minor === null ? '' : Money::toInput($vehicle->price_minor, $vehicle->currency),
            'vin' => $vehicle->vin,
            'vin_masked' => $this->maskVin($vehicle->vin),
            'pts_document_type' => $vehicle->pts_document_type?->value,
            'pts_document_label' => $vehicle->pts_document_type?->label(),
            'purchased_year' => $vehicle->purchased_year,
            'purchased_month' => $vehicle->purchased_month,
            'on_warranty' => $vehicle->on_warranty,
            'not_customs_cleared' => $vehicle->not_customs_cleared,
            'description' => $vehicle->description,
            'specs' => $this->specs($vehicle, $names, $facts),
            'options' => $equipment === null
                ? []
                : ($this->catalog->options([$equipment->id])[$equipment->id] ?? []),
            'photos' => $vehicle->photos->map(fn (VehiclePhoto $photo): array => [
                'public_id' => $photo->public_id,
                'url' => $photo->url(),
                'original_name' => $photo->original_name,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array{mark: string, model: string, generation: string, years: string|null, series: string, title: string}|null  $names
     * @return list<array{label: string, value: string, emphasis: bool}>
     */
    private function specs(StockVehicle $vehicle, ?array $names, ?ModificationFacts $facts): array
    {
        $volume = null;
        $power = null;
        $engine = null;
        $transmission = null;
        $drive = null;

        if ($facts !== null) {
            foreach ($facts->specs() as $spec) {
                match ($spec['label']) {
                    'Объём двигателя' => $volume = $spec['value'],
                    'Мощность' => $power = $spec['value'],
                    'Двигатель' => $engine = $spec['value'],
                    'Коробка передач' => $transmission = $spec['value'],
                    'Привод' => $drive = $spec['value'],
                    default => null,
                };
            }
        }

        $generation = null;

        if ($names !== null) {
            $generation = trim($names['generation'].($names['years'] ? ' ('.$names['years'].')' : ''));
        }

        $rows = [
            ['label' => 'Марка', 'value' => $names['mark'] ?? null, 'emphasis' => true],
            ['label' => 'Модель', 'value' => $names['model'] ?? null, 'emphasis' => true],
            ['label' => 'Поколение', 'value' => $generation === '' ? null : $generation, 'emphasis' => false],
            ['label' => 'Модификация', 'value' => $facts?->label(), 'emphasis' => false],
            ['label' => 'Год', 'value' => (string) $vehicle->year, 'emphasis' => true],
        ];

        if ($vehicle->condition === StockCondition::Used) {
            $rows[] = [
                'label' => 'Пробег',
                'value' => $vehicle->mileage_km === null ? null : number_format($vehicle->mileage_km, 0, ',', ' ').' км',
                'emphasis' => true,
            ];
            $rows[] = [
                'label' => 'Владельцы',
                'value' => $vehicle->owners_count === null ? null : (string) $vehicle->owners_count,
                'emphasis' => true,
            ];
        }

        $rows = [
            ...$rows,
            ['label' => 'Тип кузова', 'value' => $vehicle->body_name ?? ($names['series'] ?? null), 'emphasis' => true],
            ['label' => 'Цвет', 'value' => $vehicle->color_name, 'emphasis' => false],
            ['label' => 'VIN', 'value' => $vehicle->condition === StockCondition::Used ? $this->maskVin($vehicle->vin) : null, 'emphasis' => false],
            ['label' => 'Тип двигателя', 'value' => $engine, 'emphasis' => false],
            ['label' => 'Объём двигателя', 'value' => $volume, 'emphasis' => false],
            ['label' => 'Руль', 'value' => 'Левый', 'emphasis' => false],
            ['label' => 'КПП', 'value' => $transmission, 'emphasis' => false],
            ['label' => 'Привод', 'value' => $drive, 'emphasis' => false],
            ['label' => 'Мощность двигателя', 'value' => $power, 'emphasis' => false],
        ];

        $filled = [];

        foreach ($rows as $row) {
            if ($row['value'] === null || $row['value'] === '') {
                continue;
            }

            $filled[] = [
                'label' => $row['label'],
                'value' => $row['value'],
                'emphasis' => (bool) $row['emphasis'],
            ];
        }

        return $filled;
    }

    private function modificationFacts(StockVehicle $vehicle): ?ModificationFacts
    {
        $series = $this->catalog->series($vehicle->catalog_series_ref);

        if ($series === null) {
            return null;
        }

        $trims = $this->catalog->trims($series);

        if ($vehicle->catalog_equipment_ref !== null) {
            foreach ($trims as $trim) {
                foreach ($trim['equipments'] as $equipment) {
                    if ($equipment->external_key === $vehicle->catalog_equipment_ref) {
                        return $trim['facts'];
                    }
                }
            }
        }

        return $trims[0]['facts'] ?? null;
    }

    private function resolveEquipment(StockVehicle $vehicle): ?AutoEquipment
    {
        if ($vehicle->catalog_equipment_ref === null) {
            return null;
        }

        $series = $this->catalog->series($vehicle->catalog_series_ref);

        return $series === null ? null : $this->catalog->equipment($series, $vehicle->catalog_equipment_ref);
    }

    private function maskVin(?string $vin): ?string
    {
        if ($vin === null || $vin === '') {
            return null;
        }

        $visible = mb_substr($vin, 0, 4);

        return $visible.str_repeat('*', max(0, mb_strlen($vin) - 4));
    }
}
