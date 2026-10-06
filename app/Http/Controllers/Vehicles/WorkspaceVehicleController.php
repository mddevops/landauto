<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\SeriesPicker;
use App\Automotive\VehicleCatalog;
use App\Automotive\WorkspaceVehicleLibrary;
use App\Enums\MediaAngle;
use App\Enums\SiteStatus;
use App\Enums\WorkspacePermission;
use App\Exceptions\InvalidCatalogDataException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\Workspace;
use App\Models\WorkspaceVehicle;
use App\Support\SiteAccessResolver;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Библиотека автомобилей» of the current Workspace (D-083). Catalog rows are read-only and
 * referenced by public_id; library entries are resolved inside the current Workspace only.
 */
class WorkspaceVehicleController extends Controller
{
    public function __construct(
        private WorkspaceContext $workspaceContext,
        private VehicleCatalog $catalog,
    ) {}

    public function index(): Response
    {
        $workspace = $this->authorizeLibrary();
        $vehicles = $workspace->vehicleLibrary()->ordered()->withCount('mediaSets')->get();
        $seriesIds = $vehicles->map(fn (WorkspaceVehicle $vehicle): string => $vehicle->catalog_series_public_id)->values()->all();
        $series = $this->catalog->series($seriesIds);
        $available = AutoSeries::query()->available()->whereIn('public_id', $seriesIds)->pluck('public_id')->flip();

        return Inertia::render('workspaces/vehicles/index', [
            'vehicles' => $vehicles->map(fn (WorkspaceVehicle $vehicle): array => [
                'public_id' => $vehicle->public_id,
                'custom_name' => $vehicle->custom_name,
                'status' => $vehicle->status,
                'media_sets_count' => (int) $vehicle->getAttribute('media_sets_count'),
                'catalog' => isset($series[$vehicle->catalog_series_public_id]) ? $this->catalog->seriesTitle($series[$vehicle->catalog_series_public_id]) : null,
                'catalog_available' => isset($available[$vehicle->catalog_series_public_id]),
            ])->values()->all(),
        ]);
    }

    public function create(Request $request, SeriesPicker $picker): Response
    {
        $workspace = $this->authorizeLibrary();

        return Inertia::render('workspaces/vehicles/create', [
            'levels' => $picker->levels($request, $workspace->vehicleLibrary()->pluck('catalog_series_public_id')->flip()->all()),
        ]);
    }

    public function store(Request $request, WorkspaceVehicleLibrary $library): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $vehicle = $library->add($workspace, $request->string('series')->toString());

        return to_route('workspace.vehicles.show', $vehicle->public_id);
    }

    public function show(string $vehicle, SiteAccessResolver $siteAccess): Response
    {
        $workspace = $this->authorizeLibrary();
        $libraryVehicle = $this->vehicle($workspace, $vehicle);
        $series = AutoSeries::query()->with('generation.model.mark')->where('public_id', $libraryVehicle->catalog_series_public_id)->first();
        $selected = $libraryVehicle->mediaSets()->pluck('series_media_sets.public_id')->flip();

        return Inertia::render('workspaces/vehicles/show', [
            'vehicle' => [
                'public_id' => $libraryVehicle->public_id,
                'custom_name' => $libraryVehicle->custom_name,
                'custom_description' => $libraryVehicle->custom_description,
                'status' => $libraryVehicle->status,
                'catalog' => $series === null ? null : $this->catalog->seriesTitle($series),
            ],
            'mediaSets' => SeriesMediaSet::query()
                ->where('catalog_series_public_id', $libraryVehicle->catalog_series_public_id)
                ->active()
                ->ordered()
                ->with('images')
                ->get()
                ->map(fn (SeriesMediaSet $set): array => [
                    'public_id' => $set->public_id,
                    'name' => $set->name,
                    'swatch_hex' => $set->swatch_hex,
                    'selected' => isset($selected[$set->public_id]),
                    'preview_url' => $this->preview($set)?->url(),
                    'images_count' => $set->images->count(),
                ])->values()->all(),
            'sites' => $this->importableSites($siteAccess)
                ->map(fn (Site $site): array => [
                    'public_id' => $site->public_id,
                    'name' => $site->name,
                    'has_vehicle' => $site->vehicles()->where('catalog_series_public_id', $libraryVehicle->catalog_series_public_id)->exists(),
                ])->values()->all(),
        ]);
    }

    public function update(Request $request, string $vehicle): RedirectResponse
    {
        $libraryVehicle = $this->vehicle($this->authorizeLibrary(), $vehicle);

        $libraryVehicle->update($request->validate([
            'custom_name' => ['nullable', 'string', 'max:120'],
            'custom_description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'boolean'],
        ], [], ['custom_name' => 'Название', 'custom_description' => 'Описание', 'status' => 'Статус']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изменения сохранены.']);

        return back();
    }

    public function media(Request $request, string $vehicle): RedirectResponse
    {
        $libraryVehicle = $this->vehicle($this->authorizeLibrary(), $vehicle);

        $data = $request->validate([
            'sets' => ['present', 'array', 'max:50'],
            'sets.*' => ['string', 'size:26'],
        ]);

        try {
            /** @var list<string> $sets */
            $sets = array_values($data['sets']);
            $libraryVehicle->selectMediaSets($sets);
        } catch (InvalidCatalogDataException) {
            throw ValidationException::withMessages(['sets' => 'Выберите доступные варианты этой серии. Обновите страницу.']);
        }

        return back();
    }

    public function destroy(string $vehicle): RedirectResponse
    {
        $this->vehicle($this->authorizeLibrary(), $vehicle)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль удалён из библиотеки. Копии на сайтах не изменились.']);

        return to_route('workspace.vehicles.index');
    }

    /**
     * «Добавить на сайт»: requires the library permission, access to the destination Site and
     * import_vehicles there. Creates an independent Site vehicle without any offers.
     */
    public function copyToSite(Request $request, string $vehicle, SiteAccessResolver $siteAccess, WorkspaceVehicleLibrary $library): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $libraryVehicle = $this->vehicle($workspace, $vehicle);
        $sitePublicId = $request->string('site')->toString();
        $site = $this->importableSites($siteAccess)->first(fn (Site $site): bool => $site->public_id === $sitePublicId)
            ?? throw ValidationException::withMessages(['site' => 'Выберите доступный сайт.']);

        $library->copyToSite($libraryVehicle, $site);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Автомобиль добавлен на сайт «{$site->name}»."]);

        return back();
    }

    private function authorizeLibrary(): Workspace
    {
        Gate::authorize(WorkspacePermission::ManageWorkspaceVehicleLibrary->value);

        return $this->workspaceContext->current() ?? abort(403);
    }

    private function vehicle(Workspace $workspace, string $publicId): WorkspaceVehicle
    {
        return $workspace->vehicleLibrary()->where('public_id', $publicId)->first() ?? abort(404);
    }

    /**
     * Active Sites of the current Workspace the actor may enter and import vehicles into.
     *
     * @return Collection<int, Site>
     */
    private function importableSites(SiteAccessResolver $siteAccess): Collection
    {
        $membership = $this->workspaceContext->membership() ?? abort(403);

        return $siteAccess->scopeAccessible(Site::query(), $membership)
            ->where('status', SiteStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->filter(fn (Site $site): bool => Gate::allows('importVehicles', $site))
            ->values()
            ->toBase();
    }

    private function preview(SeriesMediaSet $set): ?SeriesMediaImage
    {
        return $set->images->sortBy(fn (SeriesMediaImage $image): int => $image->angle === MediaAngle::FrontThreeQuarter ? -1 : $image->angle->position())->first();
    }
}
