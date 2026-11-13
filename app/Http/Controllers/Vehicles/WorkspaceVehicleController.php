<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\VehiclePhotos;
use App\Automotive\VehiclePresenter;
use App\Automotive\VehicleWizard;
use App\Catalog\AutoCatalog;
use App\Catalog\CatalogPresenter;
use App\Enums\MediaAngle;
use App\Enums\SiteStatus;
use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Models\Site;
use App\Models\VehicleCollection;
use App\Models\Workspace;
use App\Models\WorkspaceVehicle;
use App\Models\WorkspaceVehicleOffer;
use App\Support\SiteAccessResolver;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer «Каталог» of the current Workspace (D-083, ADR-010): vehicles built from the auto.ru
 * catalog with own photo copies, default trims with prices and collections. Every row is
 * resolved inside the current Workspace.
 */
class WorkspaceVehicleController extends Controller
{
    public const BULK_ACTIONS = ['archive', 'restore', 'delete', 'add_to_collection', 'remove_from_collection', 'copy_to_site'];

    public function __construct(
        private WorkspaceContext $workspaceContext,
        private VehiclePresenter $vehicles,
    ) {}

    public function index(SiteAccessResolver $siteAccess): Response
    {
        $workspace = $this->authorizeLibrary();

        return Inertia::render('workspaces/vehicles/index', [
            'vehicles' => $this->vehicles->cards($workspace->vehicleLibrary()->ordered()->get()),
            'collections' => $this->collectionList($workspace),
            'sites' => $this->importableSites($siteAccess)->map(fn (Site $site): array => ['public_id' => $site->public_id, 'name' => $site->name])->values()->all(),
        ]);
    }

    public function create(Request $request, CatalogPresenter $catalog): Response
    {
        $workspace = $this->authorizeLibrary();

        return Inertia::render('workspaces/vehicles/create', [
            ...$catalog->wizard($request),
            'collections' => $this->collectionList($workspace),
            'preselectedCollection' => $request->query('collection'),
        ]);
    }

    public function store(StoreVehicleRequest $request, VehicleWizard $wizard): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $vehicle = $wizard->createInCatalog($workspace, $request->draft(), $request->strings('collections'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль добавлен в Каталог.']);

        return to_route('workspace.vehicles.show', $vehicle->public_id);
    }

    public function show(Request $request, string $vehicle, SiteAccessResolver $siteAccess, CatalogPresenter $catalog, AutoCatalog $auto): Response
    {
        $workspace = $this->authorizeLibrary();
        $libraryVehicle = $this->vehicle($workspace, $vehicle);
        $series = $auto->series($libraryVehicle->catalog_series_ref);

        return Inertia::render('workspaces/vehicles/show', [
            'vehicle' => [
                ...$this->vehicles->detail($libraryVehicle),
                'collections' => $libraryVehicle->collections()->pluck('vehicle_collections.public_id')->all(),
            ],
            'offers' => $this->vehicles->catalogOffers($libraryVehicle),
            'trims' => fn (): array => $series === null ? [] : $catalog->trims($series),
            'library' => fn (): array => $catalog->library($libraryVehicle->catalog_series_ref),
            'options' => fn (): array => $series === null ? [] : $catalog->options($series, $request->query('equipment')),
            'angles' => MediaAngle::options(),
            'collections' => $this->collectionList($workspace),
            'sites' => $this->importableSites($siteAccess)
                ->map(fn (Site $site): array => [
                    'public_id' => $site->public_id,
                    'name' => $site->name,
                    'has_vehicle' => $site->vehicles()->where('catalog_series_ref', $libraryVehicle->catalog_series_ref)->exists(),
                ])->values()->all(),
        ]);
    }

    public function update(Request $request, string $vehicle): RedirectResponse
    {
        $libraryVehicle = $this->vehicle($this->authorizeLibrary(), $vehicle);

        $libraryVehicle->update($request->validate([
            'custom_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'custom_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'boolean'],
        ], [], ['custom_name' => 'Название', 'custom_description' => 'Описание', 'status' => 'Статус']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изменения сохранены.']);

        return back();
    }

    /**
     * Replaces the default trims and prices; Sites keep their own copies.
     */
    public function offers(Request $request, string $vehicle, AutoCatalog $catalog): RedirectResponse
    {
        $libraryVehicle = $this->vehicle($this->authorizeLibrary(), $vehicle);
        $series = $catalog->series($libraryVehicle->catalog_series_ref)
            ?? throw ValidationException::withMessages(['offers' => 'Автомобиль недоступен в каталоге.']);
        $validator = Validator::make($request->all(), StoreVehicleRequest::offerRules(), [], [
            'offers.*.price' => 'Цена со скидкой',
            'offers.*.rrp' => 'Цена без скидки',
        ]);
        $input = StoreVehicleRequest::offerList($request->input('offers', []));
        $validator->after(fn ($validator) => $validator->errors()->isEmpty() ? StoreVehicleRequest::checkOffers($validator, $catalog, $series, $input) : null);
        $validator->validate();

        DB::transaction(function () use ($libraryVehicle, $input): void {
            $libraryVehicle->offers()->delete();

            foreach (StoreVehicleRequest::parseOffers($input) as $position => $offer) {
                $row = new WorkspaceVehicleOffer([
                    'catalog_equipment_ref' => $offer['equipment'],
                    'price_minor' => $offer['price_minor'],
                    'rrp_minor' => $offer['rrp_minor'],
                    'sort_order' => $position,
                ]);
                $row->vehicle()->associate($libraryVehicle)->save();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Комплектации и цены сохранены.']);

        return back();
    }

    public function collections(Request $request, string $vehicle): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $libraryVehicle = $this->vehicle($workspace, $vehicle);
        $data = $request->validate(['collections' => ['present', 'array', 'max:50'], 'collections.*' => ['string', 'size:26']]);

        $libraryVehicle->collections()->sync(
            $workspace->vehicleCollections()->whereIn('public_id', array_map('strval', $data['collections']))->pluck('id')->all(),
        );

        return back();
    }

    public function destroy(string $vehicle, VehiclePhotos $photos): RedirectResponse
    {
        $libraryVehicle = $this->vehicle($this->authorizeLibrary(), $vehicle);

        DB::transaction(function () use ($libraryVehicle, $photos): void {
            $photos->removeAll($libraryVehicle);
            $libraryVehicle->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль удалён из Каталога. Копии на сайтах не изменились.']);

        return to_route('workspace.vehicles.index');
    }

    public function bulk(Request $request, SiteAccessResolver $siteAccess, VehicleWizard $wizard, VehiclePhotos $photos): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $data = $request->validate([
            'action' => ['required', Rule::in(self::BULK_ACTIONS)],
            'vehicles' => ['required', 'array', 'min:1', 'max:500'],
            'vehicles.*' => ['string', 'size:26'],
            'collection' => ['required_if:action,add_to_collection,remove_from_collection', 'nullable', 'string', 'size:26'],
            'site' => ['required_if:action,copy_to_site', 'nullable', 'string', 'size:26'],
        ], ['vehicles.required' => 'Выберите автомобили.']);
        $vehicles = $workspace->vehicleLibrary()->whereIn('public_id', array_map('strval', $data['vehicles']))->ordered()->get();
        $action = (string) $data['action'];

        $message = match ($action) {
            'archive', 'restore' => $this->setStatus($vehicles, $action === 'restore'),
            'delete' => $this->deleteMany($vehicles, $photos),
            'add_to_collection', 'remove_from_collection' => $this->toggleCollection($workspace, $vehicles, (string) $data['collection'], $action === 'add_to_collection'),
            default => $this->copyMany($vehicles, $this->importableSite($siteAccess, (string) $data['site']), $wizard),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * «Добавить на сайт»: requires the Catalog permission, access to the destination Site and
     * import_vehicles there. Photos and default trims with prices are copied.
     */
    public function copyToSite(Request $request, string $vehicle, SiteAccessResolver $siteAccess, VehicleWizard $wizard): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $libraryVehicle = $this->vehicle($workspace, $vehicle);
        $site = $this->importableSite($siteAccess, $request->string('site')->toString());

        $wizard->copyToSite($libraryVehicle, $site, Gate::allows('editPrices', $site));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Автомобиль добавлен на сайт «{$site->name}»."]);

        return back();
    }

    /**
     * @param  Collection<int, WorkspaceVehicle>  $vehicles
     */
    private function setStatus(Collection $vehicles, bool $status): string
    {
        $vehicles->each(fn (WorkspaceVehicle $vehicle) => $vehicle->update(['status' => $status]));

        return $status ? 'Автомобили возвращены из архива.' : 'Автомобили перенесены в архив.';
    }

    /**
     * @param  Collection<int, WorkspaceVehicle>  $vehicles
     */
    private function deleteMany(Collection $vehicles, VehiclePhotos $photos): string
    {
        DB::transaction(fn () => $vehicles->each(function (WorkspaceVehicle $vehicle) use ($photos): void {
            $photos->removeAll($vehicle);
            $vehicle->delete();
        }));

        return 'Автомобили удалены из Каталога. Копии на сайтах не изменились.';
    }

    /**
     * @param  Collection<int, WorkspaceVehicle>  $vehicles
     */
    private function toggleCollection(Workspace $workspace, Collection $vehicles, string $collectionId, bool $add): string
    {
        $collection = $workspace->vehicleCollections()->where('public_id', $collectionId)->first()
            ?? throw ValidationException::withMessages(['collection' => 'Подборка не найдена.']);
        $ids = $vehicles->pluck('id')->all();
        $add ? $collection->vehicles()->syncWithoutDetaching($ids) : $collection->vehicles()->detach($ids);

        return $add ? "Автомобили добавлены в подборку «{$collection->name}»." : "Автомобили убраны из подборки «{$collection->name}».";
    }

    /**
     * @param  Collection<int, WorkspaceVehicle>  $vehicles
     */
    private function copyMany(Collection $vehicles, Site $site, VehicleWizard $wizard): string
    {
        $copied = 0;
        $skipped = 0;
        $withPrices = Gate::allows('editPrices', $site);

        foreach ($vehicles as $vehicle) {
            try {
                $wizard->copyToSite($vehicle, $site, $withPrices);
                $copied++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return $skipped === 0
            ? "На сайт «{$site->name}» добавлено автомобилей: {$copied}."
            : "На сайт «{$site->name}» добавлено автомобилей: {$copied}. Пропущено: {$skipped} — уже есть на сайте или в архиве.";
    }

    /**
     * @return list<array{public_id: string, name: string, vehicles_count: int}>
     */
    private function collectionList(Workspace $workspace): array
    {
        return array_values($workspace->vehicleCollections()->ordered()->withCount('vehicles')->get()
            ->map(fn (VehicleCollection $collection): array => [
                'public_id' => $collection->public_id,
                'name' => $collection->name,
                'vehicles_count' => (int) $collection->getAttribute('vehicles_count'),
            ])->all());
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

    private function importableSite(SiteAccessResolver $siteAccess, string $publicId): Site
    {
        return $this->importableSites($siteAccess)->first(fn (Site $site): bool => $site->public_id === $publicId)
            ?? throw ValidationException::withMessages(['site' => 'Выберите доступный сайт.']);
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
}
