<?php

namespace App\Automotive;

use App\Catalog\AutoCatalog;
use App\Enums\MediaAngle;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Support\Money;

/**
 * Automotive binding registry (P3-012, ADR-010): the only path from Site vehicles to Block
 * renderers. Builds read-only, display-ready view models; Blocks never query the catalog database
 * and never receive numeric IDs, raw money integers or hidden/unavailable rows.
 *
 * @phpstan-type Spec array{label: string, value: string}
 * @phpstan-type Offer array{public_id: string, modification: array{name: string, summary: string, specs: list<Spec>}, equipment: array{name: string}, price_label: string|null, rrp_label: string|null, availability_label: string|null, badge: string|null, benefits: list<array{label: string, amount_label: string}>, characteristics: list<array{group: string, items: list<Spec>}>, options: list<array{group: string, items: list<array{name: string, availability: string}>}>}
 * @phpstan-type Image array{public_id: string, angle: string, label: string, url: string, width: int, height: int}
 * @phpstan-type Vehicle array{public_id: string, mark: string, model: string, generation: string, years: string|null, series: string, title: string, description?: string, price_from_label: string|null, benefit_up_to_label: string|null, media: array{source: string|null, primary_angle: string|null, sets: list<array{public_id: string, name: string, swatch_hex: string|null, images: list<Image>}>, gallery: list<array{public_id: string, url: string, width: int, height: int}>}, offers: list<Offer>}
 */
final class VehicleBindings
{
    public function __construct(
        private AutoCatalog $catalog,
        private VehicleMediaResolver $media,
    ) {}

    /**
     * Visible vehicles of the Site whose catalog Series exists, in Site order.
     *
     * @return list<Vehicle>
     */
    public function forSite(Site $site): array
    {
        return $this->build($site)['vehicles'];
    }

    /**
     * Bindings plus the exact money of each shown priced Offer, read from the same rows, for the
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

        $series = $this->catalog->seriesByRefs($vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_ref)->all());
        $vehicles = $vehicles->filter(fn (SiteVehicle $vehicle): bool => $series->has($vehicle->catalog_series_ref))->values();
        $keys = [];

        foreach ($vehicles as $vehicle) {
            $keys[$series[$vehicle->catalog_series_ref]->id] = [
                ...($keys[$series[$vehicle->catalog_series_ref]->id] ?? []),
                ...$vehicle->offers->map(fn (SiteOffer $offer): string => $offer->catalog_equipment_ref)->all(),
            ];
        }

        $equipments = $this->catalog->equipments($keys);
        $modifications = $equipments->map(fn (AutoEquipment $equipment) => $equipment->modification)->unique('id')->values();
        $facts = $this->catalog->facts($modifications);
        $characteristics = $this->catalog->characteristics($modifications->map(fn (AutoModification $modification): int => $modification->id)->all());
        $options = $this->catalog->options($equipments->map(fn (AutoEquipment $equipment): int => $equipment->id)->values()->all());
        $media = $this->media->resolveMany($vehicles);
        $bindings = [];
        $money = [];

        foreach ($vehicles as $vehicle) {
            /** @var AutoSeries $row */
            $row = $series[$vehicle->catalog_series_ref];
            $title = $this->catalog->title($row);
            $title['title'] = $vehicle->custom_name ?? $title['title'];
            $visible = $vehicle->offers->filter(fn (SiteOffer $offer): bool => $equipments->has("{$row->id}|{$offer->catalog_equipment_ref}"))->values();
            $priced = $visible->filter(fn (SiteOffer $offer): bool => $offer->price_minor !== null);
            $cheapest = $priced->sortBy('price_minor')->first();
            $bestBenefit = $visible->map(fn (SiteOffer $offer): int => (int) $offer->benefits->sum('amount_minor'))->max();
            $offerBindings = [];

            foreach ($visible as $offer) {
                /** @var AutoEquipment $equipment */
                $equipment = $equipments["{$row->id}|{$offer->catalog_equipment_ref}"];
                $modificationFacts = $facts[$equipment->modification_id];
                $offerBindings[] = [
                    'public_id' => $offer->public_id,
                    'modification' => [
                        'name' => $modificationFacts->label(),
                        'summary' => $modificationFacts->summary(),
                        'specs' => $modificationFacts->specs(),
                    ],
                    'equipment' => ['name' => $equipment->displayName()],
                    'price_label' => $offer->price_minor === null ? null : Money::format($offer->price_minor, $offer->currency),
                    'rrp_label' => $offer->rrp_minor !== null && $offer->rrp_minor > (int) $offer->price_minor
                        ? Money::format($offer->rrp_minor, $offer->currency)
                        : null,
                    'availability_label' => $offer->availability?->label(),
                    'badge' => $offer->badge,
                    'benefits' => array_values($offer->benefits->map(fn ($benefit): array => [
                        'label' => $benefit->label ?? $benefit->type->label(),
                        'amount_label' => Money::format($benefit->amount_minor, $offer->currency),
                    ])->all()),
                    'characteristics' => $characteristics[$equipment->modification_id] ?? [],
                    'options' => $options[$equipment->id] ?? [],
                ];

                if ($offer->price_minor !== null) {
                    $money[$offer->public_id] = ['price_minor' => $offer->price_minor, 'currency' => $offer->currency];
                }
            }

            $vehicleMedia = $media[$vehicle->public_id];
            $bindings[] = [
                'public_id' => $vehicle->public_id,
                ...$title,
                // Present only when set, so manifests of vehicles without one keep their pre-P8 hash.
                ...($vehicle->custom_description === null ? [] : ['description' => $vehicle->custom_description]),
                'price_from_label' => $cheapest === null ? null : Money::format((int) $cheapest->price_minor, $cheapest->currency),
                'benefit_up_to_label' => ! $bestBenefit ? null : Money::format($bestBenefit, $cheapest->currency ?? Money::DEFAULT_CURRENCY),
                'media' => [
                    ...$vehicleMedia,
                    'sets' => array_map(fn (array $set): array => [
                        ...$set,
                        'images' => array_map(fn (array $image): array => [
                            ...$image,
                            'label' => MediaAngle::from($image['angle'])->label(),
                        ], $set['images']),
                    ], $vehicleMedia['sets']),
                ],
                'offers' => $offerBindings,
            ];
        }

        return ['vehicles' => $bindings, 'offers' => $money];
    }
}
