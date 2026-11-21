<?php

namespace App\Automotive;

use App\Catalog\AutoCatalog;
use App\Enums\MediaAngle;
use App\Enums\SiteSectionType;
use App\Enums\StockCondition;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteStockListing;
use App\Models\StockVehicle;
use App\Models\VehiclePhoto;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Stock → Vehicle binding shape for Used / Specials (X-038). Blocks reuse the same registry fields.
 *
 * @phpstan-import-type Vehicle from VehicleBindings
 */
final class StockBindings
{
    public function __construct(private AutoCatalog $catalog) {}

    /**
     * Ready Stock explicitly placed on this Site for the given section type.
     *
     * @return array{vehicles: list<Vehicle>, offers: array<string, array{price_minor: int, currency: string}>}
     */
    public function forPublication(Site $site, SiteSectionType $type): array
    {
        $condition = match ($type) {
            SiteSectionType::Used => StockCondition::Used,
            SiteSectionType::Specials => StockCondition::New,
            default => null,
        };

        if ($condition === null) {
            return ['vehicles' => [], 'offers' => []];
        }

        $listings = SiteStockListing::query()
            ->visibleFor($site, $condition)
            ->with([
                'stockVehicle.photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'photos' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            ])
            ->get();

        if ($listings->isEmpty()) {
            return ['vehicles' => [], 'offers' => []];
        }

        $series = $this->catalog->seriesByRefs($listings->map(fn (SiteStockListing $listing): string => $listing->stockVehicle->catalog_series_ref)->all());
        $bindings = [];
        $money = [];

        foreach ($listings as $listing) {
            $vehicle = $listing->stockVehicle;
            $priceMinor = $listing->price_minor ?? $vehicle->price_minor;
            $currency = $listing->price_minor === null ? $vehicle->currency : ($listing->currency ?? $vehicle->currency);
            $row = $series->get($vehicle->catalog_series_ref);

            if (! $row instanceof AutoSeries) {
                continue;
            }

            $title = $this->catalog->title($row);
            $offerId = $vehicle->public_id;
            $priceLabel = $priceMinor === null
                ? null
                : Money::format($priceMinor, $currency);
            $equipment = $vehicle->catalog_equipment_ref === null
                ? null
                : $this->catalog->equipment($row, $vehicle->catalog_equipment_ref);
            $facts = $this->catalog->trims($row);
            $trim = null;

            if ($equipment !== null) {
                foreach ($facts as $candidate) {
                    foreach ($candidate['equipments'] as $item) {
                        if ($item->id === $equipment->id) {
                            $trim = $candidate;
                            break 2;
                        }
                    }
                }
            }

            $trim ??= $facts[0] ?? null;
            $modificationFacts = $trim['facts'] ?? null;
            $options = $equipment === null
                ? []
                : ($this->catalog->options([$equipment->id])[$equipment->id] ?? []);
            $characteristics = $modificationFacts === null
                ? []
                : ($this->catalog->characteristics([$trim['modification']->id])[$trim['modification']->id] ?? []);

            $offers = [[
                'public_id' => $offerId,
                'modification' => [
                    'name' => $modificationFacts?->label() ?? ($vehicle->body_name ?? $row->name),
                    'summary' => $modificationFacts?->summary() ?? '',
                    'specs' => $modificationFacts?->specs() ?? [],
                ],
                'equipment' => ['name' => $equipment?->displayName() ?? 'Комплектация'],
                'price_label' => $priceLabel,
                'rrp_label' => null,
                'availability_label' => $vehicle->availability->label(),
                'badge' => $vehicle->condition->label(),
                'benefits' => [],
                'characteristics' => $characteristics,
                'options' => $options,
            ]];

            if ($priceMinor !== null) {
                $money[$offerId] = [
                    'price_minor' => $priceMinor,
                    'currency' => $currency,
                ];
            }

            $bindings[] = [
                'public_id' => $vehicle->public_id,
                'mark' => $title['mark'],
                'model' => $title['model'],
                'generation' => $title['generation'],
                'years' => $title['years'],
                'series' => $title['series'],
                'title' => trim("{$title['title']} {$vehicle->year}"),
                ...($vehicle->description === null ? [] : ['description' => $vehicle->description]),
                'price_from_label' => $priceLabel,
                'benefit_up_to_label' => null,
                'media' => $this->media($vehicle, $listing->photos_custom ? $listing->photos : $vehicle->photos, $listing->photos_custom ? $listing->public_id : $vehicle->public_id),
                'offers' => $offers,
            ];
        }

        return ['vehicles' => $bindings, 'offers' => $money];
    }

    /**
     * Merge Stock bindings for every Stock-fed section enabled on the Site.
     *
     * @return array{vehicles: list<Vehicle>, offers: array<string, array{price_minor: int, currency: string}>}
     */
    public function forEnabledSections(Site $site): array
    {
        $vehicles = [];
        $offers = [];
        $seen = [];

        foreach ($site->sections()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get() as $section) {
            if (! $section->type->fromStock()) {
                continue;
            }

            $batch = $this->forPublication($site, $section->type);

            foreach ($batch['vehicles'] as $vehicle) {
                if (isset($seen[$vehicle['public_id']])) {
                    continue;
                }
                $seen[$vehicle['public_id']] = true;
                $vehicles[] = $vehicle;
            }

            $offers = [...$offers, ...$batch['offers']];
        }

        return ['vehicles' => $vehicles, 'offers' => $offers];
    }

    /**
     * @param  Collection<int, VehiclePhoto>  $photos
     * @return array{source: string|null, primary_angle: string|null, sets: list<array{public_id: string, name: string, swatch_hex: string|null, images: list<array{public_id: string, angle: string, label: string, url: string, width: int, height: int}>}>, gallery: list<array{public_id: string, url: string, width: int, height: int}>}
     */
    private function media(StockVehicle $vehicle, Collection $photos, string $setId): array
    {
        $gallery = array_values($photos->map(fn (VehiclePhoto $photo): array => [
            'public_id' => $photo->public_id,
            'url' => $photo->url(),
            'width' => $photo->width,
            'height' => $photo->height,
        ])->all());

        $images = array_values($photos->map(fn (VehiclePhoto $photo): array => [
            'public_id' => $photo->public_id,
            'angle' => MediaAngle::SideLeft->value,
            'label' => MediaAngle::SideLeft->label(),
            'url' => $photo->url(),
            'width' => $photo->width,
            'height' => $photo->height,
        ])->all());

        return [
            'source' => 'stock',
            'primary_angle' => MediaAngle::SideLeft->value,
            'sets' => $images === [] ? [] : [[
                'public_id' => $setId,
                'name' => $vehicle->color_name,
                'swatch_hex' => $vehicle->color_swatch_hex,
                'images' => $images,
            ]],
            'gallery' => $gallery,
        ];
    }
}
