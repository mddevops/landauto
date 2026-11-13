<?php

namespace App\Automotive;

use App\Catalog\AutoCatalog;
use App\Enums\MediaAngle;
use App\Enums\PublishedAssetKind;
use App\Models\PublishedAssetReference;
use App\Models\SeriesGalleryImage;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\SiteVehicle;
use App\Models\VehicleColor;
use App\Models\VehiclePhoto;
use App\Models\WorkspaceVehicle;
use App\Support\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Customer-owned vehicle photos (ADR-010 §6): copies of platform colours and gallery photos,
 * the catalog series image and the customer's own uploads. Every copy is a new file, so later
 * platform or source-vehicle changes never alter this vehicle. A photo used by a Published
 * Version keeps its file and row (soft-deleted).
 */
final class VehiclePhotos
{
    public function __construct(private AutoCatalog $catalog) {}

    /**
     * @param  list<string>  $colorIds  platform media set public IDs, in display order
     * @param  list<string>  $galleryIds  platform gallery image public IDs, in display order
     */
    public function copyFromLibrary(WorkspaceVehicle|SiteVehicle $vehicle, array $colorIds, array $galleryIds, bool $catalogImage = false): void
    {
        $colorIds = array_values(array_unique($colorIds));
        $galleryIds = array_values(array_unique($galleryIds));
        $sets = SeriesMediaSet::query()
            ->where('catalog_series_ref', $vehicle->catalog_series_ref)
            ->active()
            ->whereIn('public_id', $colorIds)
            ->with('images')
            ->get()
            ->keyBy('public_id');
        $gallery = SeriesGalleryImage::query()
            ->where('catalog_series_ref', $vehicle->catalog_series_ref)
            ->whereIn('public_id', $galleryIds)
            ->get()
            ->keyBy('public_id');

        if ($sets->count() !== count($colorIds) || $gallery->count() !== count($galleryIds)) {
            throw ValidationException::withMessages(['photos' => 'Выберите фото из медиатеки этой модели.']);
        }

        $colorOrder = (int) $vehicle->colors()->max('sort_order');
        $photoOrder = (int) $vehicle->photos()->max('sort_order');

        foreach ($colorIds as $id) {
            /** @var SeriesMediaSet $set */
            $set = $sets[$id];
            $color = $this->newColor($vehicle, $set->name, $set->swatch_hex, ++$colorOrder);

            foreach ($set->images->sortBy(fn (SeriesMediaImage $image): int => $image->angle->position()) as $image) {
                $this->store($vehicle, SeriesMediaImage::DISK, $image->path, [
                    'angle' => $image->angle,
                    'source' => VehiclePhoto::SOURCE_CATALOG,
                    'original_name' => $image->original_name,
                    'mime_type' => $image->mime_type,
                    'sort_order' => $image->angle->position(),
                ], $color);
            }
        }

        foreach ($galleryIds as $id) {
            /** @var SeriesGalleryImage $image */
            $image = $gallery[$id];
            $this->store($vehicle, SeriesGalleryImage::DISK, $image->path, [
                'source' => VehiclePhoto::SOURCE_CATALOG,
                'original_name' => $image->original_name,
                'mime_type' => $image->mime_type,
                'sort_order' => ++$photoOrder,
            ]);
        }

        if ($catalogImage) {
            $this->copyCatalogImage($vehicle, ++$photoOrder);
        }
    }

    /**
     * Customer upload: into a colour angle (replacing the previous photo of that angle) or the gallery.
     */
    public function upload(WorkspaceVehicle|SiteVehicle $vehicle, UploadedFile $file, ?VehicleColor $color = null, ?MediaAngle $angle = null): VehiclePhoto
    {
        $mime = (string) $file->getMimeType();
        $extension = ImageUpload::TYPES[$mime] ?? throw ValidationException::withMessages(['file' => 'Загрузите изображение JPG, PNG или WebP.']);

        if ($color !== null && $angle !== null) {
            $color->photos()->where('angle', $angle->value)->get()->each(fn (VehiclePhoto $photo) => $this->remove($photo));
        }

        $photo = $this->newPhoto($vehicle, $color, [
            'angle' => $color === null ? null : $angle,
            'source' => VehiclePhoto::SOURCE_UPLOAD,
            'original_name' => Str::limit(trim($file->getClientOriginalName()) ?: 'image', 250, ''),
            'mime_type' => $mime,
            'sort_order' => $color === null ? ((int) $vehicle->photos()->max('sort_order')) + 1 : ($angle?->position() ?? 0),
        ], $extension);
        $disk = Storage::disk(VehiclePhoto::DISK);
        $disk->putFileAs(dirname($photo->path), $file, basename($photo->path));

        return $this->persist($photo, (string) $disk->get($photo->path));
    }

    public function addColor(WorkspaceVehicle|SiteVehicle $vehicle, string $name, ?string $swatch): VehicleColor
    {
        return $this->newColor($vehicle, $name, $swatch, ((int) $vehicle->colors()->max('sort_order')) + 1);
    }

    /**
     * Copy every colour and photo of one vehicle into another (Catalog → Site, Site → Site).
     */
    public function copyAll(WorkspaceVehicle|SiteVehicle $from, WorkspaceVehicle|SiteVehicle $to): void
    {
        $from->loadMissing('colors');
        $colors = [];

        foreach ($from->colors as $color) {
            $colors[$color->id] = $this->newColor($to, $color->name, $color->swatch_hex, $color->sort_order);
        }

        foreach ($from->photos()->get() as $photo) {
            $this->store($to, VehiclePhoto::DISK, $photo->path, [
                'angle' => $photo->angle,
                'source' => $photo->source,
                'original_name' => $photo->original_name,
                'mime_type' => $photo->mime_type,
                'sort_order' => $photo->sort_order,
            ], $photo->vehicle_color_id === null ? null : ($colors[$photo->vehicle_color_id] ?? null));
        }

        $to->forceFill(['primary_angle' => $from->primary_angle])->save();
    }

    /**
     * Replace all photos of a vehicle with copies of another vehicle's photos.
     */
    public function replaceAll(WorkspaceVehicle|SiteVehicle $from, WorkspaceVehicle|SiteVehicle $to): void
    {
        $this->removeAll($to);
        $this->copyAll($from, $to);
    }

    public function remove(VehiclePhoto $photo): void
    {
        if (PublishedAssetReference::isReferenced(PublishedAssetKind::VehiclePhoto, $photo->public_id)) {
            $photo->delete();

            return;
        }

        $path = $photo->path;
        $photo->forceDelete();
        Storage::disk(VehiclePhoto::DISK)->delete($path);
    }

    public function removeColor(VehicleColor $color): void
    {
        DB::transaction(function () use ($color): void {
            $color->photos()->get()->each(fn (VehiclePhoto $photo) => $this->remove($photo));
            $color->delete();
        });
    }

    public function removeAll(WorkspaceVehicle|SiteVehicle $vehicle): void
    {
        DB::transaction(function () use ($vehicle): void {
            $vehicle->photos()->get()->each(fn (VehiclePhoto $photo) => $this->remove($photo));
            $vehicle->colors()->delete();
        });
    }

    private function copyCatalogImage(WorkspaceVehicle|SiteVehicle $vehicle, int $order): void
    {
        $path = $this->catalog->series($vehicle->catalog_series_ref)?->image;
        $disk = Storage::disk('auto_catalog');

        if ($path === null || ! $disk->exists($path)) {
            return;
        }

        $mime = (string) $disk->mimeType($path);

        if (! isset(ImageUpload::TYPES[$mime])) {
            return;
        }

        $this->store($vehicle, 'auto_catalog', $path, [
            'source' => VehiclePhoto::SOURCE_CATALOG,
            'original_name' => basename($path),
            'mime_type' => $mime,
            'sort_order' => $order,
        ]);
    }

    /**
     * @param  array{angle?: MediaAngle|null, source: string, original_name: string, mime_type: string, sort_order: int}  $attributes
     */
    private function store(WorkspaceVehicle|SiteVehicle $vehicle, string $disk, string $path, array $attributes, ?VehicleColor $color = null): ?VehiclePhoto
    {
        $contents = Storage::disk($disk)->get($path);
        $extension = ImageUpload::TYPES[$attributes['mime_type']] ?? null;

        if ($contents === null || $extension === null) {
            return null;
        }

        $photo = $this->newPhoto($vehicle, $color, $attributes, $extension);
        Storage::disk(VehiclePhoto::DISK)->put($photo->path, $contents);

        return $this->persist($photo, $contents);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newPhoto(WorkspaceVehicle|SiteVehicle $vehicle, ?VehicleColor $color, array $attributes, string $extension): VehiclePhoto
    {
        $photo = new VehiclePhoto($attributes);
        $photo->public_id = $photo->newUniqueId();
        $photo->workspace_id = $vehicle->photoWorkspaceId();
        $photo->setAttribute($vehicle->photoOwnerColumn(), $vehicle->getKey());
        $photo->vehicle_color_id = $color?->id;
        $photo->path = "vehicle-photos/{$photo->workspace_id}/{$photo->public_id}.{$extension}";

        return $photo;
    }

    private function persist(VehiclePhoto $photo, string $contents): VehiclePhoto
    {
        $info = @getimagesizefromstring($contents);
        $photo->size_bytes = strlen($contents);
        $photo->width = $info === false ? 0 : (int) $info[0];
        $photo->height = $info === false ? 0 : (int) $info[1];

        try {
            $photo->save();
        } catch (Throwable $exception) {
            Storage::disk(VehiclePhoto::DISK)->delete($photo->path);

            throw $exception;
        }

        return $photo;
    }

    private function newColor(WorkspaceVehicle|SiteVehicle $vehicle, string $name, ?string $swatch, int $order): VehicleColor
    {
        $color = new VehicleColor(['name' => $name, 'swatch_hex' => $swatch, 'sort_order' => $order]);
        $color->workspace_id = $vehicle->photoWorkspaceId();
        $color->setAttribute($vehicle->photoOwnerColumn(), $vehicle->getKey());
        $color->save();

        return $color;
    }
}
