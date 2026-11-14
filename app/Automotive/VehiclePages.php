<?php

namespace App\Automotive;

use App\Catalog\AutoCatalog;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteVehicle;
use Illuminate\Support\Str;

/**
 * Addresses of Site vehicles inside a vehicle section (ADR-011 §4): mark and model segments come
 * from the auto.ru `url` of the catalog row, the last segment is the vehicle's own slug, unique
 * among vehicles of the same model and editable by the customer.
 *
 * @phpstan-type VehiclePage array{public_id: string, mark: array{name: string, segment: string}, model: array{name: string, segment: string}, slug: string, title: string}
 */
final class VehiclePages
{
    public const SLUG_MAX = 100;

    public function __construct(private AutoCatalog $catalog) {}

    /**
     * Visible vehicles of the Site with their address segments, in Site order.
     *
     * @return list<VehiclePage>
     */
    public function forSite(Site $site): array
    {
        $this->ensureSlugs($site);

        $vehicles = $site->vehicles()->where('status', true)->ordered()->get();
        $series = $this->catalog->seriesByRefs($vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_ref)->all());
        $pages = [];

        foreach ($vehicles as $vehicle) {
            $row = $series->get($vehicle->catalog_series_ref);

            if (! $row instanceof AutoSeries || $vehicle->slug === null) {
                continue;
            }

            $model = $row->generation->model;
            $pages[] = [
                'public_id' => $vehicle->public_id,
                'mark' => ['name' => $model->mark->name, 'segment' => self::segment($model->mark->url)],
                'model' => ['name' => $model->name, 'segment' => self::segment($model->url)],
                'slug' => $vehicle->slug,
                'title' => $vehicle->custom_name ?? trim("{$model->mark->name} {$model->name} {$row->generation->name} {$row->name}"),
            ];
        }

        return $pages;
    }

    /**
     * Gives every vehicle without an address one built from its generation and body.
     */
    public function ensureSlugs(Site $site): void
    {
        if (! $site->vehicles()->whereNull('slug')->exists()) {
            return;
        }

        $vehicles = $site->vehicles()->ordered()->get();
        $series = $this->catalog->seriesByRefs($vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_ref)->all());
        $taken = [];

        foreach ($vehicles as $vehicle) {
            $row = $series->get($vehicle->catalog_series_ref);

            if ($row instanceof AutoSeries && $vehicle->slug !== null) {
                $taken[$row->generation->model_id][$vehicle->slug] = true;
            }
        }

        foreach ($vehicles as $vehicle) {
            $row = $series->get($vehicle->catalog_series_ref);

            if (! $row instanceof AutoSeries || $vehicle->slug !== null) {
                continue;
            }

            $base = self::baseSlug($row);
            $slug = $base;

            for ($suffix = 2; isset($taken[$row->generation->model_id][$slug]); $suffix++) {
                $slug = "{$base}-{$suffix}";
            }

            $taken[$row->generation->model_id][$slug] = true;
            $vehicle->slug = $slug;
            $vehicle->save();
        }
    }

    /**
     * Whether another vehicle of the same catalog model already uses the address.
     */
    public function slugTaken(SiteVehicle $vehicle, string $slug): bool
    {
        $row = $this->catalog->series($vehicle->catalog_series_ref);

        if ($row === null) {
            return false;
        }

        $others = SiteVehicle::query()
            ->where('site_id', $vehicle->site_id)
            ->whereKeyNot($vehicle->getKey())
            ->where('slug', $slug)
            ->get();

        if ($others->isEmpty()) {
            return false;
        }

        $series = $this->catalog->seriesByRefs($others->map(fn (SiteVehicle $other): string => $other->catalog_series_ref)->all());

        return $series->contains(fn (AutoSeries $other): bool => $other->generation->model_id === $row->generation->model_id);
    }

    /**
     * Changes the address and keeps the previous one for a 301 redirect.
     */
    public function rename(SiteVehicle $vehicle, string $slug): void
    {
        $previous = $vehicle->slug;

        if ($previous === $slug) {
            return;
        }

        $vehicle->slug = $slug;
        $vehicle->save();
        $vehicle->slugRedirects()->where('slug', $slug)->delete();

        if ($previous !== null) {
            $vehicle->slugRedirects()->firstOrCreate(['slug' => $previous]);
        }
    }

    public static function baseSlug(AutoSeries $series): string
    {
        $name = (string) preg_replace('/рестайлинг/iu', 'restyling', "{$series->generation->name} {$series->name}");

        return rtrim(Str::limit(Str::slug($name), self::SLUG_MAX - 10, ''), '-') ?: 'auto';
    }

    /**
     * auto.ru URLs use underscores (`alfa_romeo`); Site addresses use hyphens only.
     */
    public static function segment(string $url): string
    {
        return Str::slug(str_replace('_', '-', $url)) ?: 'auto';
    }
}
