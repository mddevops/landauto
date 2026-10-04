<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\VehicleCatalog;
use App\Catalog\CatalogLevel;
use App\Catalog\CatalogReferences;
use App\Enums\BenefitType;
use App\Enums\MediaAngle;
use App\Enums\OfferAvailability;
use App\Exceptions\InvalidCatalogDataException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\Catalog\CatalogModel;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteOfferBenefit;
use App\Models\SiteVehicle;
use App\Support\DesignerScope;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer «Автомобили» section: Site-owned vehicles at catalog Series level (D-104). The
 * catalog is read-only here; every catalog reference is validated on the server.
 */
class SiteVehicleController extends Controller
{
    private const PICKER_LEVELS = [CatalogLevel::Marks, CatalogLevel::Models, CatalogLevel::Generations, CatalogLevel::Series];

    public function __construct(
        private DesignerScope $scope,
        private VehicleCatalog $catalog,
    ) {}

    public function index(Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('viewVehicles', $site);

        $vehicles = $site->vehicles()->ordered()->withCount(['offers', 'mediaSets'])->get();
        $seriesIds = $vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_public_id)->values()->all();
        $series = $this->catalog->series($seriesIds);
        $available = AutoSeries::query()->available()->whereIn('public_id', $seriesIds)->pluck('public_id')->flip();

        return Inertia::render('sites/vehicles/index', [
            'site' => $this->site($site),
            'vehicles' => $vehicles->map(fn (SiteVehicle $vehicle): array => [
                'public_id' => $vehicle->public_id,
                'status' => $vehicle->status,
                'sort_order' => $vehicle->sort_order,
                'offers_count' => (int) $vehicle->getAttribute('offers_count'),
                'media_sets_count' => (int) $vehicle->getAttribute('media_sets_count'),
                'catalog' => isset($series[$vehicle->catalog_series_public_id]) ? $this->catalog->seriesTitle($series[$vehicle->catalog_series_public_id]) : null,
                'catalog_available' => isset($available[$vehicle->catalog_series_public_id]),
            ])->values()->all(),
            'can' => ['editVehicles' => Gate::allows('editVehicles', $site)],
        ]);
    }

    public function create(Request $request, Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('editVehicles', $site);

        $added = $site->vehicles()->pluck('catalog_series_public_id')->flip();
        $levels = [];
        $parent = null;

        foreach (self::PICKER_LEVELS as $level) {
            if ($level->parent() !== null && $parent === null) {
                break;
            }

            $query = $level->modelClass()::query()->scopes(['available'])->ordered();

            if ($parent !== null) {
                $query->where((string) $level->parentKey(), $parent->getKey());
            }

            $items = $query->get();
            $requested = $request->query($level->selectionKey());
            $selected = is_string($requested)
                ? $items->first(fn (CatalogModel $item): bool => $item->public_id === $requested)
                : null;

            $levels[] = [
                'key' => $level->value,
                'label' => $level->label(),
                'selectionKey' => $level->selectionKey(),
                'selected' => $selected?->public_id,
                'items' => $items->map(fn (CatalogModel $item): array => [
                    'public_id' => $item->public_id,
                    'name' => (string) $item->getAttribute('name'),
                    'added' => $level === CatalogLevel::Series && isset($added[$item->public_id]),
                ])->values()->all(),
            ];

            $parent = $level === CatalogLevel::Series ? null : $selected;
        }

        return Inertia::render('sites/vehicles/create', [
            'site' => $this->site($site),
            'levels' => $levels,
        ]);
    }

    public function store(Request $request, Site $site, CatalogReferences $references): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('editVehicles', $site);

        $series = $references->series($request->string('series')->toString(), availableOnly: true)
            ?? throw ValidationException::withMessages(['series' => 'Выберите серию из каталога.']);

        if ($site->vehicles()->where('catalog_series_public_id', $series->public_id)->exists()) {
            throw ValidationException::withMessages(['series' => 'Этот автомобиль уже добавлен на сайт.']);
        }

        $vehicle = new SiteVehicle(['status' => true, 'sort_order' => ((int) $site->vehicles()->max('sort_order')) + 1]);
        $vehicle->catalog_series_public_id = $series->public_id;
        $vehicle->site()->associate($site)->save();

        return to_route('sites.vehicles.show', [$site, $vehicle]);
    }

    public function show(Site $site, SiteVehicle $vehicle): Response
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('viewVehicles', $site);

        $series = AutoSeries::query()->with('generation.model.mark')->where('public_id', $vehicle->catalog_series_public_id)->first();
        $selected = $vehicle->mediaSets()->pluck('series_media_sets.public_id')->flip();
        $offers = $vehicle->offers()->ordered()->with('benefits')->get();
        $equipments = $this->catalog->equipments($offers->map(fn (SiteOffer $offer): string => $offer->catalog_equipment_public_id)->values()->all());

        return Inertia::render('sites/vehicles/show', [
            'site' => $this->site($site),
            'vehicle' => [
                'public_id' => $vehicle->public_id,
                'status' => $vehicle->status,
                'sort_order' => $vehicle->sort_order,
                'catalog' => $series === null ? null : $this->catalog->seriesTitle($series),
            ],
            'mediaSets' => SeriesMediaSet::query()
                ->where('catalog_series_public_id', $vehicle->catalog_series_public_id)
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
            'modifications' => $series === null ? [] : AutoModification::query()
                ->available()
                ->where('series_id', $series->id)
                ->ordered()
                ->with(['equipments' => fn ($query) => $query->where('status', true)->ordered()])
                ->get()
                ->map(fn (AutoModification $modification): array => [
                    'public_id' => $modification->public_id,
                    'name' => $modification->name,
                    'summary' => $this->catalog->modificationSummary($modification),
                    'equipments' => $modification->equipments->map(fn (AutoEquipment $equipment): array => [
                        'public_id' => $equipment->public_id,
                        'name' => $equipment->name,
                    ])->values()->all(),
                ])->values()->all(),
            'offers' => $offers->map(function (SiteOffer $offer) use ($equipments): array {
                $equipment = $equipments[$offer->catalog_equipment_public_id] ?? null;

                return [
                    'public_id' => $offer->public_id,
                    'equipment' => $equipment === null ? null : [
                        'public_id' => $equipment->public_id,
                        'name' => $equipment->name,
                        'modification' => $equipment->modification->public_id,
                        'modification_name' => $equipment->modification->name,
                    ],
                    'price' => Money::toInput($offer->price_minor, $offer->currency),
                    'price_label' => Money::format($offer->price_minor, $offer->currency),
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
                ];
            })->values()->all(),
            'choices' => [
                'availability' => array_map(fn (OfferAvailability $case): array => ['value' => $case->value, 'label' => $case->label()], OfferAvailability::cases()),
                'benefitTypes' => array_map(fn (BenefitType $case): array => ['value' => $case->value, 'label' => $case->label()], BenefitType::cases()),
            ],
            'can' => [
                'editVehicles' => Gate::allows('editVehicles', $site),
                'editPrices' => Gate::allows('editPrices', $site),
                'editBenefits' => Gate::allows('editBenefits', $site),
            ],
        ]);
    }

    public function update(Request $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('editVehicles', $site);

        $vehicle->update($request->validate([
            'status' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ], [], ['status' => 'Показ на сайте', 'sort_order' => 'Сортировка']));

        return back();
    }

    public function media(Request $request, Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('editVehicles', $site);

        $data = $request->validate([
            'sets' => ['present', 'array', 'max:50'],
            'sets.*' => ['string', 'size:26'],
        ]);

        try {
            /** @var list<string> $sets */
            $sets = array_values($data['sets']);
            $vehicle->selectMediaSets($sets);
        } catch (InvalidCatalogDataException) {
            throw ValidationException::withMessages(['sets' => 'Выберите доступные варианты этой серии. Обновите страницу.']);
        }

        return back();
    }

    public function destroy(Site $site, SiteVehicle $vehicle): RedirectResponse
    {
        $this->scope->vehicle($site, $vehicle);
        Gate::authorize('editVehicles', $site);

        $vehicle->delete();

        return to_route('sites.vehicles.index', $site);
    }

    /**
     * @return array{public_id: string, name: string}
     */
    private function site(Site $site): array
    {
        return ['public_id' => $site->public_id, 'name' => $site->name];
    }

    private function preview(SeriesMediaSet $set): ?SeriesMediaImage
    {
        return $set->images->sortBy(fn (SeriesMediaImage $image): int => $image->angle === MediaAngle::FrontThreeQuarter ? -1 : $image->angle->position())->first();
    }
}
