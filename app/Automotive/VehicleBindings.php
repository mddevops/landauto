<?php

namespace App\Automotive;

use App\Enums\Catalog\DriveType;
use App\Enums\Catalog\EngineType;
use App\Enums\Catalog\TransmissionType;
use App\Enums\MediaAngle;
use App\Models\Catalog\AutoCharacteristicValue;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoOptionValue;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Support\Money;

/**
 * Automotive binding registry (P3-012): the only path from Site vehicles to Block renderers.
 * Builds read-only, display-ready view models; Blocks never query the catalog database and
 * never receive numeric IDs, raw money integers or hidden/unavailable rows.
 *
 * @phpstan-type Spec array{label: string, value: string}
 * @phpstan-type Offer array{public_id: string, modification: array{name: string, summary: string, specs: list<Spec>}, equipment: array{name: string}, price_label: string, rrp_label: string|null, availability_label: string|null, badge: string|null, benefits: list<array{label: string, amount_label: string}>, characteristics: list<array{group: string, items: list<Spec>}>, options: list<array{group: string, items: list<array{name: string, availability: string}>}>}
 * @phpstan-type Vehicle array{public_id: string, mark: string, model: string, generation: string, series: string, title: string, price_from_label: string|null, benefit_up_to_label: string|null, media: array{source: string|null, sets: list<array{public_id: string, name: string, swatch_hex: string|null, images: list<array{public_id: string, angle: string, label: string, url: string, width: int, height: int}>}>}, offers: list<Offer>}
 */
final class VehicleBindings
{
    public function __construct(
        private VehicleCatalog $catalog,
        private VehicleMediaResolver $media,
    ) {}

    /**
     * Visible vehicles of the Site whose catalog Series is available, in Site order.
     *
     * @return list<Vehicle>
     */
    public function forSite(Site $site): array
    {
        return $this->build($site)['vehicles'];
    }

    /**
     * Bindings plus the exact money of each shown Offer, read from the same rows, for the
     * Published Version (ADR-006). The money map is server-side trusted context only.
     *
     * @return array{vehicles: list<Vehicle>, offers: array<string, array{price_minor: int, currency: string}>}
     */
    public function forPublication(Site $site): array
    {
        return $this->build($site);
    }

    /**
     * @return array{vehicles: list<Vehicle>, offers: array<string, array{price_minor: int, currency: string}>}
     */
    private function build(Site $site): array
    {
        $vehicles = $site->vehicles()
            ->where('status', true)
            ->ordered()
            ->with(['offers' => fn ($query) => $query->where('status', true)->ordered()->with('benefits')])
            ->get();

        if ($vehicles->isEmpty()) {
            return ['vehicles' => [], 'offers' => []];
        }

        $series = AutoSeries::query()
            ->available()
            ->with('generation.model.mark')
            ->whereIn('public_id', $vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_public_id)->all())
            ->get()
            ->keyBy('public_id');
        $vehicles = $vehicles->filter(fn (SiteVehicle $vehicle): bool => $series->has($vehicle->catalog_series_public_id))->values();
        $offers = $vehicles->flatMap(fn (SiteVehicle $vehicle) => $vehicle->offers);
        $equipments = AutoEquipment::query()
            ->available()
            ->with('modification')
            ->whereIn('public_id', $offers->map(fn (SiteOffer $offer): string => $offer->catalog_equipment_public_id)->unique()->values()->all())
            ->get()
            ->keyBy('public_id');
        $characteristics = $this->characteristics($equipments->modelKeys());
        $options = $this->options($equipments->modelKeys());
        $media = $this->media->resolveMany($vehicles);
        $bindings = [];
        $money = [];

        foreach ($vehicles as $vehicle) {
            $title = $this->catalog->seriesTitle($series[$vehicle->catalog_series_public_id]);
            $visibleOffers = $vehicle->offers->filter(fn (SiteOffer $offer): bool => $equipments->has($offer->catalog_equipment_public_id))->values();
            $cheapest = $visibleOffers->sortBy('price_minor')->first();
            $bestBenefit = $visibleOffers->map(fn (SiteOffer $offer): int => (int) $offer->benefits->sum('amount_minor'))->max();
            $offerBindings = [];

            foreach ($visibleOffers as $offer) {
                $offerBindings[] = $this->offer($offer, $equipments[$offer->catalog_equipment_public_id], $characteristics, $options);
                $money[$offer->public_id] = ['price_minor' => $offer->price_minor, 'currency' => $offer->currency];
            }

            $bindings[] = [
                'public_id' => $vehicle->public_id,
                ...$title,
                'price_from_label' => $cheapest === null ? null : Money::format($cheapest->price_minor, $cheapest->currency),
                'benefit_up_to_label' => $cheapest === null || ! $bestBenefit ? null : Money::format($bestBenefit, $cheapest->currency),
                'media' => [
                    'source' => $media[$vehicle->public_id]['source'],
                    'sets' => array_map(fn (array $set): array => [
                        ...$set,
                        'images' => array_map(fn (array $image): array => [
                            ...$image,
                            'label' => MediaAngle::from($image['angle'])->label(),
                        ], $set['images']),
                    ], $media[$vehicle->public_id]['sets']),
                ],
                'offers' => $offerBindings,
            ];
        }

        return ['vehicles' => $bindings, 'offers' => $money];
    }

    /**
     * @param  array<int, list<array{group: string, items: list<array{label: string, value: string}>}>>  $characteristics
     * @param  array<int, list<array{group: string, items: list<array{name: string, availability: string}>}>>  $options
     * @return Offer
     */
    private function offer(SiteOffer $offer, AutoEquipment $equipment, array $characteristics, array $options): array
    {
        $benefits = [];

        foreach ($offer->benefits as $benefit) {
            $benefits[] = [
                'label' => $benefit->label ?? $benefit->type->label(),
                'amount_label' => Money::format($benefit->amount_minor, $offer->currency),
            ];
        }

        return [
            'public_id' => $offer->public_id,
            'modification' => [
                'name' => $equipment->modification->name,
                'summary' => $this->catalog->modificationSummary($equipment->modification),
                'specs' => $this->specs($equipment->modification),
            ],
            'equipment' => ['name' => $equipment->name],
            'price_label' => Money::format($offer->price_minor, $offer->currency),
            'rrp_label' => $offer->rrp_minor !== null && $offer->rrp_minor > $offer->price_minor
                ? Money::format($offer->rrp_minor, $offer->currency)
                : null,
            'availability_label' => $offer->availability?->label(),
            'badge' => $offer->badge,
            'benefits' => $benefits,
            'characteristics' => $characteristics[$equipment->id] ?? [],
            'options' => $options[$equipment->id] ?? [],
        ];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function specs(AutoModification $modification): array
    {
        $specs = [];
        $add = function (string $label, ?string $value) use (&$specs): void {
            if ($value !== null && $value !== '') {
                $specs[] = ['label' => $label, 'value' => $value];
            }
        };

        $add('Объём двигателя', $modification->engine_volume === null ? null : $modification->engine_volume.' см³');
        $add('Мощность', $modification->engine_power === null ? null : VehicleCatalog::decimal($modification->engine_power).' л.с.');
        $add('Двигатель', EngineType::tryFrom((string) $modification->engine)?->label());
        $add('Коробка передач', TransmissionType::tryFrom((string) $modification->transmission)?->label());
        $add('Привод', DriveType::tryFrom((string) $modification->drive)?->label());
        $add('Расход', $modification->consumption_100_km === null ? null : VehicleCatalog::decimal($modification->consumption_100_km).' л/100 км');
        $add('Разгон 0–100 км/ч', $modification->acceleration_0_100 === null ? null : VehicleCatalog::decimal($modification->acceleration_0_100).' с');

        return $specs;
    }

    /**
     * @param  array<int, int|string>  $equipmentIds
     * @return array<int, list<array{group: string, items: list<array{label: string, value: string}>}>>
     */
    private function characteristics(array $equipmentIds): array
    {
        $values = AutoCharacteristicValue::query()
            ->whereIn('equipment_id', $equipmentIds)
            ->with('characteristic.parent')
            ->get()
            ->sortBy(fn (AutoCharacteristicValue $value): array => [
                $value->characteristic->parent->sort_order ?? 0,
                $value->characteristic->parent_id ?? 0,
                $value->characteristic->sort_order,
                $value->characteristic_id,
            ]);
        $grouped = [];

        foreach ($values as $value) {
            $definition = $value->characteristic;
            $group = $definition->parent->name ?? 'Прочее';
            $display = is_numeric($value->value) ? VehicleCatalog::decimal($value->value) : $value->value;
            $grouped[$value->equipment_id][$group][] = [
                'label' => $definition->name,
                'value' => $definition->unit === null ? $display : "{$display} {$definition->unit}",
            ];
        }

        return array_map(fn (array $groups): array => array_map(
            fn (string $group, array $items): array => ['group' => $group, 'items' => $items],
            array_keys($groups),
            array_values($groups),
        ), $grouped);
    }

    /**
     * Only known option rows; a missing row means unknown and is not shown.
     *
     * @param  array<int, int|string>  $equipmentIds
     * @return array<int, list<array{group: string, items: list<array{name: string, availability: string}>}>>
     */
    private function options(array $equipmentIds): array
    {
        $values = AutoOptionValue::query()
            ->whereIn('equipment_id', $equipmentIds)
            ->with('option.parent')
            ->get()
            ->sortBy(fn (AutoOptionValue $value): array => [
                $value->option->parent->sort_order ?? 0,
                $value->option->parent_id ?? 0,
                $value->option->sort_order,
                $value->option_id,
            ]);
        $grouped = [];

        foreach ($values as $value) {
            $group = $value->option->parent->name ?? 'Прочее';
            $grouped[$value->equipment_id][$group][] = [
                'name' => $value->option->name,
                'availability' => $value->is_base ? 'standard' : 'optional',
            ];
        }

        return array_map(fn (array $groups): array => array_map(
            fn (string $group, array $items): array => ['group' => $group, 'items' => $items],
            array_keys($groups),
            array_values($groups),
        ), $grouped);
    }
}
