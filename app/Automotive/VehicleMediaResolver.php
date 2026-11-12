<?php

namespace App\Automotive;

use App\Enums\MediaAngle;
use App\Models\SiteVehicle;
use App\Models\VehiclePhoto;
use App\Models\WorkspaceVehicle;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Photos a vehicle shows (ADR-010): its own colours with angle photos and its gallery. Colours
 * without photos are skipped. The primary angle falls back to the preferred angle and then to
 * the first available one.
 *
 * @phpstan-type Image array{public_id: string, angle: string, url: string, width: int, height: int}
 * @phpstan-type GalleryImage array{public_id: string, url: string, width: int, height: int}
 * @phpstan-type Media array{source: string|null, primary_angle: string|null, sets: list<array{public_id: string, name: string, swatch_hex: string|null, images: list<Image>}>, gallery: list<GalleryImage>}
 */
final class VehicleMediaResolver
{
    public const SOURCE_SITE = 'site';

    /**
     * @param  iterable<WorkspaceVehicle|SiteVehicle>  $vehicles
     * @return array<string, Media>
     */
    public function resolveMany(iterable $vehicles): array
    {
        $vehicles = EloquentCollection::make($vehicles);

        if ($vehicles->isEmpty()) {
            return [];
        }

        $vehicles->loadMissing(['colors', 'photos']);
        $resolved = [];

        foreach ($vehicles as $vehicle) {
            $resolved[$vehicle->public_id] = $this->present($vehicle);
        }

        return $resolved;
    }

    /**
     * @return Media
     */
    public function resolve(WorkspaceVehicle|SiteVehicle $vehicle): array
    {
        return $this->resolveMany([$vehicle])[$vehicle->public_id];
    }

    /**
     * First photo to show on a card: the primary angle of the first colour, else the first gallery photo.
     *
     * @param  Media  $media
     * @return array{public_id: string, url: string, width: int, height: int}|null
     */
    public static function cover(array $media): ?array
    {
        foreach ($media['sets'] as $set) {
            foreach ($set['images'] as $image) {
                if ($image['angle'] === $media['primary_angle']) {
                    return $image;
                }
            }
        }

        return $media['sets'][0]['images'][0] ?? $media['gallery'][0] ?? null;
    }

    /**
     * @return Media
     */
    private function present(WorkspaceVehicle|SiteVehicle $vehicle): array
    {
        $byColor = $vehicle->photos->groupBy(fn (VehiclePhoto $photo): int => (int) $photo->vehicle_color_id);
        $sets = [];
        $angles = [];

        foreach ($vehicle->colors as $color) {
            $images = $this->images(array_values($byColor->get($color->id, collect())->all()));

            if ($images === []) {
                continue;
            }

            foreach ($images as $image) {
                $angles[$image['angle']] = true;
            }

            $sets[] = ['public_id' => $color->public_id, 'name' => $color->name, 'swatch_hex' => $color->swatch_hex, 'images' => $images];
        }

        $gallery = [];

        foreach ($byColor->get(0, collect()) as $photo) {
            $gallery[] = ['public_id' => $photo->public_id, 'url' => $photo->url(), 'width' => $photo->width, 'height' => $photo->height];
        }

        $primary = $vehicle->primary_angle?->value;

        if ($primary === null || ! isset($angles[$primary])) {
            $primary = isset($angles[MediaAngle::PREFERRED->value]) ? MediaAngle::PREFERRED->value : (array_key_first($angles) ?: null);
        }

        return [
            'source' => $sets === [] && $gallery === [] ? null : self::SOURCE_SITE,
            'primary_angle' => $primary,
            'sets' => $sets,
            'gallery' => $gallery,
        ];
    }

    /**
     * @param  list<VehiclePhoto>  $photos
     * @return list<Image>
     */
    private function images(array $photos): array
    {
        $photos = array_filter($photos, fn (VehiclePhoto $photo): bool => $photo->angle !== null);
        usort($photos, fn (VehiclePhoto $a, VehiclePhoto $b): int => $a->angle?->position() <=> $b->angle?->position());

        return array_map(fn (VehiclePhoto $photo): array => [
            'public_id' => $photo->public_id,
            'angle' => (string) $photo->angle?->value,
            'url' => $photo->url(),
            'width' => $photo->width,
            'height' => $photo->height,
        ], $photos);
    }
}
