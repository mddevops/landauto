<?php

namespace App\Automotive;

use App\Catalog\CatalogReferences;
use App\Models\Site;
use App\Models\SiteVehicle;
use App\Models\Workspace;
use App\Models\WorkspaceVehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Workspace Vehicle Library (D-083): explicit copies between the library and Site vehicles.
 * Only the Series, reusable name / description and platform media-set selection are copied;
 * commercial data (offers, prices, benefits, availability, badges) is never copied either way,
 * and nothing syncs afterwards. Callers authorize the actor and resolve both sides inside the
 * current Workspace before calling.
 */
final class WorkspaceVehicleLibrary
{
    public function __construct(private CatalogReferences $references) {}

    public function add(Workspace $workspace, string $seriesPublicId): WorkspaceVehicle
    {
        $series = $this->references->series($seriesPublicId, availableOnly: true)
            ?? throw ValidationException::withMessages(['series' => 'Выберите серию из каталога.']);

        if ($workspace->vehicleLibrary()->where('catalog_series_public_id', $series->public_id)->exists()) {
            throw ValidationException::withMessages(['series' => 'Этот автомобиль уже есть в библиотеке.']);
        }

        $vehicle = new WorkspaceVehicle(['status' => true, 'sort_order' => ((int) $workspace->vehicleLibrary()->max('sort_order')) + 1]);
        $vehicle->catalog_series_public_id = $series->public_id;
        $vehicle->workspace()->associate($workspace);

        try {
            $vehicle->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['series' => 'Этот автомобиль уже есть в библиотеке.']);
        }

        return $vehicle;
    }

    public function copyToSite(WorkspaceVehicle $source, Site $site): SiteVehicle
    {
        if ($source->workspace_id !== $site->workspace_id) {
            throw ValidationException::withMessages(['site' => 'Выберите сайт этого пространства.']);
        }

        if (! $source->status) {
            throw ValidationException::withMessages(['site' => 'Автомобиль в архиве библиотеки. Верните его, чтобы добавить на сайт.']);
        }

        if ($this->references->series($source->catalog_series_public_id, availableOnly: true) === null) {
            throw ValidationException::withMessages(['site' => 'Серия больше недоступна в каталоге.']);
        }

        if ($site->vehicles()->where('catalog_series_public_id', $source->catalog_series_public_id)->exists()) {
            throw ValidationException::withMessages(['site' => 'Этот автомобиль уже есть на выбранном сайте.']);
        }

        try {
            $vehicle = DB::transaction(function () use ($source, $site): SiteVehicle {
                $vehicle = new SiteVehicle([
                    'status' => true,
                    'sort_order' => ((int) $site->vehicles()->max('sort_order')) + 1,
                    'custom_name' => $source->custom_name,
                    'custom_description' => $source->custom_description,
                ]);
                $vehicle->catalog_series_public_id = $source->catalog_series_public_id;
                $vehicle->source_workspace_vehicle_id = $source->id;
                $vehicle->site()->associate($site)->save();
                $vehicle->selectMediaSets($source->activeMediaSetPublicIds());

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
     * Save a Site vehicle into the library. An existing library entry for the same Series is
     * only updated when the caller explicitly confirms the overwrite.
     *
     * @return array{vehicle: WorkspaceVehicle, created: bool}
     */
    public function saveFromSite(SiteVehicle $source, Workspace $workspace, bool $overwrite): array
    {
        $site = $source->site;

        if ($site === null || $site->workspace_id !== $workspace->id) {
            throw ValidationException::withMessages(['library' => 'Автомобиль недоступен.']);
        }

        $existing = $workspace->vehicleLibrary()->where('catalog_series_public_id', $source->catalog_series_public_id)->first();

        if ($existing !== null && ! $overwrite) {
            throw ValidationException::withMessages(['library' => 'Этот автомобиль уже есть в библиотеке. Подтвердите обновление.']);
        }

        try {
            $vehicle = DB::transaction(function () use ($source, $workspace, $existing): WorkspaceVehicle {
                $vehicle = $existing ?? new WorkspaceVehicle([
                    'status' => true,
                    'sort_order' => ((int) $workspace->vehicleLibrary()->max('sort_order')) + 1,
                ]);
                $vehicle->fill(['custom_name' => $source->custom_name, 'custom_description' => $source->custom_description]);

                if (! $vehicle->exists) {
                    $vehicle->catalog_series_public_id = $source->catalog_series_public_id;
                    $vehicle->workspace()->associate($workspace);
                }

                $vehicle->save();
                $vehicle->selectMediaSets($source->activeMediaSetPublicIds());

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['library' => 'Этот автомобиль уже есть в библиотеке. Подтвердите обновление.']);
        }

        Log::info('workspace.vehicle_saved_to_library', [
            'workspace' => $workspace->public_id,
            'workspace_vehicle' => $vehicle->public_id,
            'site_vehicle' => $source->public_id,
            'updated' => $existing !== null,
        ]);

        return ['vehicle' => $vehicle, 'created' => $existing === null];
    }
}
