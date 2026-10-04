<?php

namespace App\Automotive;

use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\SiteVehicle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Resolves which prepared media a SiteVehicle shows (P3-011):
 * Site selection → Workspace library (not implemented in Phase 3) → Global active Series media sets.
 * Only active sets that have at least one image are returned; files are referenced, never copied.
 */
final class VehicleMediaResolver
{
    public const SOURCE_SITE = 'site';

    public const SOURCE_GLOBAL = 'global';

    /**
     * @param  iterable<SiteVehicle>  $vehicles
     * @return array<string, array{source: string|null, sets: list<array{public_id: string, name: string, swatch_hex: string|null, images: list<array{angle: string, url: string, width: int, height: int}>}>}>
     */
    public function resolveMany(iterable $vehicles): array
    {
        $vehicles = EloquentCollection::make($vehicles);

        if ($vehicles->isEmpty()) {
            return [];
        }

        $vehicles->loadMissing(['mediaSets' => fn ($query) => $query->active()]);
        $globalSets = SeriesMediaSet::query()
            ->active()
            ->whereIn('catalog_series_public_id', $vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_public_id)->unique()->values()->all())
            ->ordered()
            ->get()
            ->groupBy('catalog_series_public_id');
        $images = [];
        $setIds = $vehicles->flatMap(fn (SiteVehicle $vehicle) => $vehicle->mediaSets->modelKeys())
            ->merge($globalSets->flatten()->map(fn (SeriesMediaSet $set): int => $set->id))
            ->unique()
            ->values()
            ->all();

        foreach (SeriesMediaImage::query()->whereIn('series_media_set_id', $setIds)->get() as $image) {
            $images[$image->series_media_set_id][] = $image;
        }

        $resolved = [];

        foreach ($vehicles as $vehicle) {
            $site = $this->present($vehicle->mediaSets->filter(fn (SeriesMediaSet $set): bool => $set->status), $images);
            $global = $site === [] ? $this->present($globalSets->get($vehicle->catalog_series_public_id, collect()), $images) : [];

            $resolved[$vehicle->public_id] = match (true) {
                $site !== [] => ['source' => self::SOURCE_SITE, 'sets' => $site],
                $global !== [] => ['source' => self::SOURCE_GLOBAL, 'sets' => $global],
                default => ['source' => null, 'sets' => []],
            };
        }

        return $resolved;
    }

    /**
     * @return array{source: string|null, sets: list<array{public_id: string, name: string, swatch_hex: string|null, images: list<array{angle: string, url: string, width: int, height: int}>}>}
     */
    public function resolve(SiteVehicle $vehicle): array
    {
        return $this->resolveMany([$vehicle])[$vehicle->public_id];
    }

    /**
     * @param  Collection<int, SeriesMediaSet>  $sets
     * @param  array<int, list<SeriesMediaImage>>  $images
     * @return list<array{public_id: string, name: string, swatch_hex: string|null, images: list<array{angle: string, url: string, width: int, height: int}>}>
     */
    private function present(Collection $sets, array $images): array
    {
        $presented = [];

        foreach ($sets as $set) {
            $setImages = [];
            $ordered = $images[$set->id] ?? [];
            usort($ordered, fn (SeriesMediaImage $a, SeriesMediaImage $b): int => $a->angle->position() <=> $b->angle->position());

            foreach ($ordered as $image) {
                $setImages[] = [
                    'angle' => $image->angle->value,
                    'url' => $image->url(),
                    'width' => $image->width,
                    'height' => $image->height,
                ];
            }

            if ($setImages !== []) {
                $presented[] = [
                    'public_id' => $set->public_id,
                    'name' => $set->name,
                    'swatch_hex' => $set->swatch_hex,
                    'images' => $setImages,
                ];
            }
        }

        return $presented;
    }
}
