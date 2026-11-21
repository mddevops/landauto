<?php

namespace App\Automotive;

use App\Catalog\AutoCatalog;
use App\Enums\SiteSectionType;
use App\Enums\StockCondition;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteSection;
use App\Models\SiteStockListing;

/**
 * Addresses of Ready Stock vehicles inside Used / Specials (ADR-011 §4, ADR-012 / X-038).
 * Same VehiclePage shape as catalog; last segment = publicPathKey().
 *
 * @phpstan-import-type VehiclePage from VehiclePages
 */
final class StockVehiclePages
{
    public function __construct(private AutoCatalog $catalog) {}

    /**
     * @return list<VehiclePage>
     */
    public function forSection(SiteSection $section): array
    {
        if (! $section->type->fromStock()) {
            return [];
        }

        $site = $section->site()->firstOrFail();

        return $this->forSite($site, $section->type);
    }

    /**
     * @return list<VehiclePage>
     */
    public function forSite(Site $site, SiteSectionType $type): array
    {
        $condition = match ($type) {
            SiteSectionType::Used => StockCondition::Used,
            SiteSectionType::Specials => StockCondition::New,
            default => null,
        };

        if ($condition === null) {
            return [];
        }

        $listings = SiteStockListing::query()
            ->visibleFor($site, $condition)
            ->with('stockVehicle')
            ->get();

        if ($listings->isEmpty()) {
            return [];
        }

        $series = $this->catalog->seriesByRefs($listings->map(fn (SiteStockListing $listing): string => $listing->stockVehicle->catalog_series_ref)->all());
        $pages = [];
        $taken = [];

        foreach ($listings as $listing) {
            $vehicle = $listing->stockVehicle;
            $row = $series->get($vehicle->catalog_series_ref);

            if (! $row instanceof AutoSeries) {
                continue;
            }

            $model = $row->generation->model;
            $mark = self::segment($model->mark->url);
            $slug = $vehicle->publicPathKey();

            if (isset($taken[$mark][$slug])) {
                continue;
            }

            $taken[$mark][$slug] = true;
            $pages[] = [
                'public_id' => $vehicle->public_id,
                'mark' => ['name' => $model->mark->name, 'segment' => $mark],
                'model' => ['name' => $model->name, 'segment' => self::segment($model->url)],
                'slug' => $slug,
                'title' => trim("{$model->mark->name} {$model->name} {$vehicle->year}"),
            ];
        }

        return $pages;
    }

    private static function segment(string $url): string
    {
        $segment = strtolower(str_replace('_', '-', $url));

        return $segment !== '' ? $segment : 'auto';
    }
}
