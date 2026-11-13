<?php

namespace App\Automotive;

use App\Enums\MediaAngle;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Models\Workspace;
use App\Models\WorkspaceVehicle;
use App\Models\WorkspaceVehicleOffer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Creates customer vehicles from the add-vehicle wizard and copies them between the customer
 * Catalog and Sites (ADR-010, D-083). Photos are copied into the new owner; Catalog default trims
 * and prices become Site Offers. Nothing syncs afterwards. Callers authorize the actor and
 * resolve every row inside the current Workspace before calling.
 *
 * @phpstan-type Draft array{series: string, custom_name: string|null, colors: list<string>, gallery: list<string>, catalog_image: bool, primary_angle: MediaAngle|null, uploads: list<UploadedFile>, offers: list<array{equipment: string, price_minor: int|null, rrp_minor: int|null}>}
 */
final class VehicleWizard
{
    public function __construct(private VehiclePhotos $photos) {}

    /**
     * @param  Draft  $draft
     * @param  list<string>  $collectionIds
     */
    public function createInCatalog(Workspace $workspace, array $draft, array $collectionIds = []): WorkspaceVehicle
    {
        $collections = $workspace->vehicleCollections()->whereIn('public_id', $collectionIds)->pluck('id')->all();

        return DB::transaction(function () use ($workspace, $draft, $collections): WorkspaceVehicle {
            $vehicle = new WorkspaceVehicle([
                'status' => true,
                'sort_order' => ((int) $workspace->vehicleLibrary()->max('sort_order')) + 1,
                'custom_name' => $draft['custom_name'],
                'primary_angle' => $draft['primary_angle'],
            ]);
            $vehicle->catalog_series_ref = $draft['series'];
            $vehicle->workspace()->associate($workspace)->save();
            $this->addPhotos($vehicle, $draft);

            foreach ($draft['offers'] as $position => $offer) {
                $row = new WorkspaceVehicleOffer([
                    'catalog_equipment_ref' => $offer['equipment'],
                    'price_minor' => $offer['price_minor'],
                    'rrp_minor' => $offer['rrp_minor'],
                    'sort_order' => $position,
                ]);
                $row->vehicle()->associate($vehicle)->save();
            }

            $vehicle->collections()->sync($collections);

            return $vehicle;
        });
    }

    /**
     * @param  Draft  $draft
     */
    public function createOnSite(Site $site, array $draft): SiteVehicle
    {
        $this->assertNotOnSite($site, $draft['series']);

        try {
            return DB::transaction(function () use ($site, $draft): SiteVehicle {
                $vehicle = $this->newSiteVehicle($site, $draft['series'], [
                    'custom_name' => $draft['custom_name'],
                    'primary_angle' => $draft['primary_angle'],
                ]);
                $this->addPhotos($vehicle, $draft);

                foreach ($draft['offers'] as $position => $offer) {
                    $this->newSiteOffer($vehicle, $offer['equipment'], $offer['price_minor'], $offer['rrp_minor'], $position);
                }

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['series' => 'Этот автомобиль уже есть на сайте.']);
        }
    }

    /**
     * «Добавить на сайт» from the customer Catalog: photos and default trims are copied, prices
     * only when the actor may set prices on the Site.
     */
    public function copyToSite(WorkspaceVehicle $source, Site $site, bool $withPrices = true): SiteVehicle
    {
        if ($source->workspace_id !== $site->workspace_id) {
            throw ValidationException::withMessages(['site' => 'Выберите сайт этого пространства.']);
        }

        if (! $source->status) {
            throw ValidationException::withMessages(['site' => 'Автомобиль в архиве Каталога. Верните его, чтобы добавить на сайт.']);
        }

        $this->assertNotOnSite($site, $source->catalog_series_ref, 'site');

        try {
            $vehicle = DB::transaction(function () use ($source, $site, $withPrices): SiteVehicle {
                $vehicle = $this->newSiteVehicle($site, $source->catalog_series_ref, [
                    'custom_name' => $source->custom_name,
                    'custom_description' => $source->custom_description,
                ]);
                $vehicle->source_workspace_vehicle_id = $source->id;
                $vehicle->save();
                $this->photos->copyAll($source, $vehicle);

                foreach ($source->offers()->get() as $position => $offer) {
                    $this->newSiteOffer(
                        $vehicle,
                        $offer->catalog_equipment_ref,
                        $withPrices ? $offer->price_minor : null,
                        $withPrices ? $offer->rrp_minor : null,
                        $position,
                    );
                }

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['site' => 'Этот автомобиль уже есть на выбранном сайте.']);
        }

        Log::info('workspace.vehicle_copied_to_site', [
            'workspace_vehicle' => $source->public_id,
            'site' => $site->public_id,
            'site_vehicle' => $vehicle->public_id,
        ]);

        return $vehicle;
    }

    /**
     * «Сохранить в Каталог»: a new Catalog vehicle with copies of the photos and the Site trims
     * and prices as defaults. When the Site vehicle came from a Catalog vehicle that still exists,
     * that vehicle is updated, but only with explicit confirmation.
     *
     * @return array{vehicle: WorkspaceVehicle, created: bool}
     */
    public function saveToCatalog(SiteVehicle $source, Workspace $workspace, bool $overwrite): array
    {
        $site = $source->site;

        if ($site === null || $site->workspace_id !== $workspace->id) {
            throw ValidationException::withMessages(['library' => 'Автомобиль недоступен.']);
        }

        $existing = $source->source_workspace_vehicle_id === null
            ? null
            : $workspace->vehicleLibrary()->whereKey($source->source_workspace_vehicle_id)->first();

        if ($existing !== null && ! $overwrite) {
            throw ValidationException::withMessages(['library' => 'Этот автомобиль уже есть в Каталоге. Подтвердите обновление.']);
        }

        $vehicle = DB::transaction(function () use ($source, $workspace, $existing): WorkspaceVehicle {
            $vehicle = $existing ?? new WorkspaceVehicle([
                'status' => true,
                'sort_order' => ((int) $workspace->vehicleLibrary()->max('sort_order')) + 1,
            ]);
            $vehicle->fill(['custom_name' => $source->custom_name, 'custom_description' => $source->custom_description]);

            if (! $vehicle->exists) {
                $vehicle->catalog_series_ref = $source->catalog_series_ref;
                $vehicle->workspace()->associate($workspace);
            }

            $vehicle->save();
            $this->photos->replaceAll($source, $vehicle);
            $vehicle->offers()->delete();

            foreach ($source->offers()->ordered()->get() as $position => $offer) {
                $row = new WorkspaceVehicleOffer([
                    'catalog_equipment_ref' => $offer->catalog_equipment_ref,
                    'price_minor' => $offer->price_minor,
                    'rrp_minor' => $offer->rrp_minor,
                    'currency' => $offer->currency,
                    'sort_order' => $position,
                ]);
                $row->vehicle()->associate($vehicle)->save();
            }

            if ($source->source_workspace_vehicle_id === null) {
                $source->forceFill(['source_workspace_vehicle_id' => $vehicle->id])->save();
            }

            return $vehicle;
        });

        Log::info('workspace.vehicle_saved_to_library', [
            'workspace' => $workspace->public_id,
            'workspace_vehicle' => $vehicle->public_id,
            'site_vehicle' => $source->public_id,
            'updated' => $existing !== null,
        ]);

        return ['vehicle' => $vehicle, 'created' => $existing === null];
    }

    /**
     * @param  Draft  $draft
     */
    private function addPhotos(WorkspaceVehicle|SiteVehicle $vehicle, array $draft): void
    {
        $this->photos->copyFromLibrary($vehicle, $draft['colors'], $draft['gallery'], $draft['catalog_image']);

        foreach ($draft['uploads'] as $file) {
            $this->photos->upload($vehicle, $file);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newSiteVehicle(Site $site, string $seriesRef, array $attributes): SiteVehicle
    {
        $vehicle = new SiteVehicle([
            'status' => true,
            'sort_order' => ((int) $site->vehicles()->max('sort_order')) + 1,
            ...$attributes,
        ]);
        $vehicle->catalog_series_ref = $seriesRef;
        $vehicle->site()->associate($site)->save();

        return $vehicle;
    }

    private function newSiteOffer(SiteVehicle $vehicle, string $equipment, ?int $price, ?int $rrp, int $position): void
    {
        $offer = new SiteOffer([
            'catalog_equipment_ref' => $equipment,
            'price_minor' => $price,
            'rrp_minor' => $rrp,
            'status' => true,
            'sort_order' => $position,
        ]);
        $offer->vehicle()->associate($vehicle)->save();
    }

    private function assertNotOnSite(Site $site, string $seriesRef, string $field = 'series'): void
    {
        if ($site->vehicles()->where('catalog_series_ref', $seriesRef)->exists()) {
            throw ValidationException::withMessages([$field => 'Этот автомобиль уже есть на сайте.']);
        }
    }
}
