<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\VehiclePhotos;
use App\Automotive\VehiclePresenter;
use App\Automotive\VehicleWizard;
use App\Catalog\AutoCatalog;
use App\Catalog\CatalogPresenter;
use App\Enums\BenefitType;
use App\Enums\MediaAngle;
use App\Enums\OfferAvailability;
use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteOfferBenefit;
use App\Models\SiteVehicle;
use App\Support\DesignerScope;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer «Автомобили» section of a Site (D-104, ADR-010): Site-owned vehicles built from the
 * auto.ru catalog with own photo copies and Site Offers. The catalog is read-only here; every
 * catalog reference is validated on the server.
 */
class SiteVehicleController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private VehiclePresenter $vehicles,
    ) {}

    public function index(Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('viewVehicles', $site);

        return Inertia::render('sites/vehicles/index', [
            'site' => $this->site($site),
            'vehicles' => $this->vehicles->cards($site->vehicles()->ordered()->get()),
            'can' => [
                'editVehicles' => Gate::allows('editVehicles', $site),
                'importVehicles' => Gate::allows('editVehicles', $site) && Gate::allows('importVehicles', $site),
            ],
        ]);
    }

    public function create(Request $request, Site $site, CatalogPresenter $catalog): Response
    {
        $this->scope->site($site);
        Gate::authorize('editVehicles', $site);

        return Inertia::render('sites/vehicles/create', [
            ...$catalog->wizard($request),
            'site' => $this->site($site),
            'taken' => $site->vehicles()->pluck('catalog_series_ref')->all(),
            'can' => ['editPrices' => Gate::allows('editPrices', $site)],
        ]);
    }

    public function store(StoreVehicleRequest $request, Site $site, VehicleWizard $wizard): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('editVehicles', $site);
        $draft = $request->draft();

        if (! Gate::allows('editPrices', $site)) {
            $draft['offers'] = array_map(fn (array $offer): array => [...$offer, 'price_minor' => null, 'rrp_minor' => null], $draft['offers']);
        }

        $vehicle = $wizard->createOnSite($site, $draft);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль добавлен на сайт.']);

        return to_route('sites.vehicles.show', [$site, $vehicle]);
    }

    public function show(Request $request, Site $site, SiteVehicle $vehicle, CatalogPresenter $catalog, AutoCatalog $auto): Response
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('viewVehicles', $site);

        $series = $auto->series($vehicle->catalog_series_ref);
        $offers = $vehicle->offers()->ordered()->with('benefits')->get();
        $labels = $this->vehicles->siteOfferLabels($vehicle, $offers);
        $canManageLibrary = Gate::allows(WorkspacePermission::ManageWorkspaceVehicleLibrary->value);

        return Inertia::render('sites/vehicles/show', [
            'site' => $this->site($site),
            'vehicle' => [
                ...$this->vehicles->detail($vehicle),
                'in_library' => $canManageLibrary && $vehicle->source_workspace_vehicle_id !== null
                    && $site->workspace->vehicleLibrary()->whereKey($vehicle->source_workspace_vehicle_id)->exists(),
            ],
            'offers' => $offers->map(fn (SiteOffer $offer): array => [
                'public_id' => $offer->public_id,
                ...$labels[$offer->public_id],
                'price' => $offer->price_minor === null ? '' : Money::toInput($offer->price_minor, $offer->currency),
                'price_label' => $offer->price_minor === null ? null : Money::format($offer->price_minor, $offer->currency),
                'rrp' => $offer->rrp_minor === null ? '' : Money::toInput($offer->rrp_minor, $offer->currency),
                'availability' => $offer->availability?->value,
                'badge' => $offer->badge,
                'status' => $offer->status,
                'sort_order' => $offer->sort_order,
                'benefits' => $offer->benefits->map(fn (SiteOfferBenefit $benefit): array => [
                    'type' => $benefit->type->value,
                    'amount' => Money::toInput($benefit->amount_minor, $offer->currency),
                    'label' => $benefit->label,
                ])->values()->all(),
            ])->values()->all(),
            'trims' => fn (): array => $series === null ? [] : $catalog->trims($series),
            'library' => fn (): array => $catalog->library($vehicle->catalog_series_ref),
            'options' => fn (): array => $series === null ? [] : $catalog->options($series, $request->query('equipment')),
            'angles' => MediaAngle::options(),
            'choices' => [
                'availability' => array_map(fn (OfferAvailability $case): array => ['value' => $case->value, 'label' => $case->label()], OfferAvailability::cases()),
                'benefitTypes' => array_map(fn (BenefitType $case): array => ['value' => $case->value, 'label' => $case->label()], BenefitType::cases()),
            ],
            'can' => [
                'editVehicles' => Gate::allows('editVehicles', $site),
                'editPrices' => Gate::allows('editPrices', $site),
                'editBenefits' => Gate::allows('editBenefits', $site),
                'manageLibrary' => $canManageLibrary,
            ],
        ]);
    }

    public function update(Request $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('editVehicles', $site);

        $vehicle->update($request->validate([
            'status' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'custom_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'custom_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], [], [
            'status' => 'Показ на сайте',
            'sort_order' => 'Сортировка',
            'custom_name' => 'Название',
            'custom_description' => 'Описание',
        ]));

        return back();
    }

    /**
     * «Сохранить в Каталог»: photos, trims and prices become a Catalog vehicle. The Catalog
     * vehicle this one came from is only updated with explicit confirmation.
     */
    public function saveToLibrary(Request $request, Site $site, SiteVehicle $vehicle, VehicleWizard $wizard): RedirectResponse
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('viewVehicles', $site);
        Gate::authorize(WorkspacePermission::ManageWorkspaceVehicleLibrary->value);

        $result = $wizard->saveToCatalog($vehicle, $site->workspace, $request->boolean('overwrite'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['created'] ? 'Автомобиль сохранён в Каталог.' : 'Автомобиль в Каталоге обновлён.',
        ]);

        return back();
    }

    public function destroy(Site $site, SiteVehicle $vehicle, VehiclePhotos $photos): RedirectResponse
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('editVehicles', $site);

        DB::transaction(function () use ($vehicle, $photos): void {
            $photos->removeAll($vehicle);
            $vehicle->delete();
        });

        return to_route('sites.vehicles.index', $site);
    }

    /**
     * @return array{public_id: string, name: string}
     */
    private function site(Site $site): array
    {
        return ['public_id' => $site->public_id, 'name' => $site->name];
    }
}
