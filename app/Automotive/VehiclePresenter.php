<?php

namespace App\Automotive;

use App\Catalog\AutoCatalog;
use App\Catalog\CatalogPresenter;
use App\Catalog\ModificationFacts;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoSeries;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\VehicleColor;
use App\Models\VehiclePhoto;
use App\Models\WorkspaceVehicle;
use App\Models\WorkspaceVehicleOffer;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Browser-safe view models of customer vehicles (customer Catalog and Sites): list cards with a
 * cover photo and the vehicle page with editable colours, gallery and trims.
 *
 * @phpstan-type Card array{public_id: string, title: string, custom_name: string|null, mark: array{url: string, name: string, logo: string|null}|null, model: array{url: string, name: string}|null, generation: string|null, series: string|null, years: string|null, status: bool, sort_order: int, cover: string|null, swatches: list<array{name: string, hex: string|null}>, photos_count: int, offers_count: int, price_from: string|null, catalog_available: bool, collections: list<string>}
 */
final class VehiclePresenter
{
    public function __construct(
        private AutoCatalog $catalog,
        private CatalogPresenter $presenter,
        private VehicleMediaResolver $media,
    ) {}

    /**
     * @param  iterable<WorkspaceVehicle|SiteVehicle>  $vehicles
     * @return list<Card>
     */
    public function cards(iterable $vehicles): array
    {
        $vehicles = EloquentCollection::make($vehicles);

        if ($vehicles->isEmpty()) {
            return [];
        }

        $vehicles->loadMissing(['colors', 'photos', 'offers']);

        if ($vehicles->first() instanceof WorkspaceVehicle) {
            $vehicles->loadMissing('collections');
        }

        $series = $this->catalog->seriesByRefs($vehicles->map(fn (WorkspaceVehicle|SiteVehicle $vehicle): string => $vehicle->catalog_series_ref)->all());
        $media = $this->media->resolveMany($vehicles);

        return array_values($vehicles->map(function (WorkspaceVehicle|SiteVehicle $vehicle) use ($series, $media): array {
            $found = $series->get($vehicle->catalog_series_ref);
            $prices = $vehicle->offers->pluck('price_minor')->filter(fn ($price): bool => $price !== null);
            $currency = $vehicle->offers->first()->currency ?? Money::DEFAULT_CURRENCY;

            return [
                'public_id' => $vehicle->public_id,
                'title' => $this->title($vehicle, $found),
                'custom_name' => $vehicle->custom_name,
                ...$this->catalogParts($found),
                'status' => $vehicle->status,
                'sort_order' => $vehicle->sort_order,
                'cover' => VehicleMediaResolver::cover($media[$vehicle->public_id])['url'] ?? ($found === null ? null : $this->catalog->imageUrl($found->image)),
                'swatches' => array_values($vehicle->colors->map(fn (VehicleColor $color): array => ['name' => $color->name, 'hex' => $color->swatch_hex])->all()),
                'photos_count' => $vehicle->photos->count(),
                'offers_count' => $vehicle->offers->count(),
                'price_from' => $prices->isEmpty() ? null : 'от '.Money::format((int) $prices->min(), $currency),
                'catalog_available' => $found !== null,
                'collections' => $vehicle instanceof WorkspaceVehicle
                    ? array_values($vehicle->collections->pluck('public_id')->map(fn ($id): string => (string) $id)->all())
                    : [],
            ];
        })->all());
    }

    /**
     * Header, photos and default trims of one vehicle for its page.
     *
     * @return array<string, mixed>
     */
    public function detail(WorkspaceVehicle|SiteVehicle $vehicle): array
    {
        $series = $this->catalog->series($vehicle->catalog_series_ref);
        $vehicle->loadMissing(['colors', 'photos']);
        $byColor = $vehicle->photos->groupBy(fn (VehiclePhoto $photo): int => (int) $photo->vehicle_color_id);

        return [
            'public_id' => $vehicle->public_id,
            'title' => $this->title($vehicle, $series),
            'custom_name' => $vehicle->custom_name,
            'custom_description' => $vehicle->custom_description,
            'status' => $vehicle->status,
            'sort_order' => $vehicle->sort_order,
            'primary_angle' => $vehicle->primary_angle?->value,
            'catalog' => $series === null ? null : $this->presenter->series($series),
            'catalog_available' => $series !== null,
            'colors' => array_values($vehicle->colors->map(fn (VehicleColor $color): array => [
                'public_id' => $color->public_id,
                'name' => $color->name,
                'swatch_hex' => $color->swatch_hex,
                'photos' => array_values($byColor->get($color->id, collect())
                    ->filter(fn (VehiclePhoto $photo): bool => $photo->angle !== null)
                    ->sortBy(fn (VehiclePhoto $photo): int => (int) $photo->angle?->position())
                    ->map(fn (VehiclePhoto $photo): array => ['public_id' => $photo->public_id, 'angle' => (string) $photo->angle?->value, 'url' => $photo->url()])
                    ->all()),
            ])->all()),
            'gallery' => array_values($byColor->get(0, collect())
                ->map(fn (VehiclePhoto $photo): array => ['public_id' => $photo->public_id, 'url' => $photo->url()])
                ->all()),
        ];
    }

    /**
     * Default trims of a customer Catalog vehicle with prices as form inputs.
     *
     * @return list<array{public_id: string, equipment: string, name: string, modification: string, summary: string, available: bool, price: string, rrp: string, price_label: string|null}>
     */
    public function catalogOffers(WorkspaceVehicle $vehicle): array
    {
        $offers = $vehicle->offers()->get();
        $resolved = $this->resolveEquipments($vehicle->catalog_series_ref, array_values($offers->map(fn (WorkspaceVehicleOffer $offer): string => $offer->catalog_equipment_ref)->all()));

        return array_values($offers->map(fn (WorkspaceVehicleOffer $offer): array => [
            'public_id' => $offer->public_id,
            ...$this->equipmentLabel($resolved, $offer->catalog_equipment_ref),
            'price' => $offer->price_minor === null ? '' : Money::toInput($offer->price_minor, $offer->currency),
            'rrp' => $offer->rrp_minor === null ? '' : Money::toInput($offer->rrp_minor, $offer->currency),
            'price_label' => $offer->price_minor === null ? null : Money::format($offer->price_minor, $offer->currency),
        ])->all());
    }

    /**
     * @param  iterable<SiteOffer>  $offers
     * @return array<string, array{equipment: string, name: string, modification: string, summary: string, available: bool}>
     */
    public function siteOfferLabels(SiteVehicle $vehicle, iterable $offers): array
    {
        $keys = [];

        foreach ($offers as $offer) {
            $keys[$offer->public_id] = $offer->catalog_equipment_ref;
        }

        $resolved = $this->resolveEquipments($vehicle->catalog_series_ref, array_values($keys));

        return array_map(fn (string $key): array => $this->equipmentLabel($resolved, $key), $keys);
    }

    /**
     * @param  list<string>  $keys
     * @return array{equipments: array<string, AutoEquipment>, facts: array<int, ModificationFacts>}
     */
    private function resolveEquipments(string $seriesRef, array $keys): array
    {
        $series = $this->catalog->series($seriesRef);

        if ($series === null || $keys === []) {
            return ['equipments' => [], 'facts' => []];
        }

        $equipments = $this->catalog->equipments([$series->id => $keys])
            ->keyBy(fn (AutoEquipment $equipment): string => $equipment->external_key);

        return [
            'equipments' => $equipments->all(),
            'facts' => $this->catalog->facts($equipments->map(fn (AutoEquipment $equipment) => $equipment->modification)->unique('id')->values()),
        ];
    }

    /**
     * @param  array{equipments: array<string, AutoEquipment>, facts: array<int, ModificationFacts>}  $resolved
     * @return array{equipment: string, name: string, modification: string, summary: string, available: bool}
     */
    private function equipmentLabel(array $resolved, string $key): array
    {
        $equipment = $resolved['equipments'][$key] ?? null;
        $facts = $equipment === null ? null : ($resolved['facts'][$equipment->modification_id] ?? null);

        return [
            'equipment' => $key,
            'name' => $equipment?->displayName() ?? 'Комплектация недоступна',
            'modification' => $facts?->label() ?? '',
            'summary' => $facts?->summary() ?? '',
            'available' => $equipment !== null,
        ];
    }

    private function title(WorkspaceVehicle|SiteVehicle $vehicle, ?AutoSeries $series): string
    {
        if ($vehicle->custom_name !== null && $vehicle->custom_name !== '') {
            return $vehicle->custom_name;
        }

        return $series === null ? 'Автомобиль недоступен в каталоге' : $this->catalog->title($series)['title'];
    }

    /**
     * @return array{mark: array{url: string, name: string, logo: string|null}|null, model: array{url: string, name: string}|null, generation: string|null, series: string|null, years: string|null}
     */
    private function catalogParts(?AutoSeries $series): array
    {
        if ($series === null) {
            return ['mark' => null, 'model' => null, 'generation' => null, 'series' => null, 'years' => null];
        }

        $model = $series->generation->model;

        return [
            'mark' => ['url' => $model->mark->url, 'name' => $model->mark->name, 'logo' => $this->catalog->imageUrl($model->mark->logo_min)],
            'model' => ['url' => $model->url, 'name' => $model->name],
            'generation' => $series->generation->name,
            'series' => $series->name,
            'years' => $series->generation->years(),
        ];
    }
}
