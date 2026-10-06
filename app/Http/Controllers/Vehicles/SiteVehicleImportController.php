<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\SiteVehicleCopier;
use App\Automotive\VehicleCatalog;
use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Support\DesignerScope;
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
 * «Импортировать с другого сайта»: explicit copy of vehicles from another Site of the same
 * Workspace into the current Site. The actor needs access to both Sites, view rights on the
 * source and import + edit rights on the destination; offers and benefits additionally need
 * edit_prices / edit_benefits on the destination.
 */
class SiteVehicleImportController extends Controller
{
    private const MAX_VEHICLES = 50;

    public function __construct(
        private DesignerScope $scope,
        private WorkspaceContext $workspaceContext,
        private SiteAccessResolver $siteAccess,
        private VehicleCatalog $catalog,
    ) {}

    public function create(Request $request, Site $site): Response
    {
        $this->authorizeDestination($site);

        $sources = $this->sources($site);
        $requested = $request->query('source');
        $source = is_string($requested) ? $sources->first(fn (Site $candidate): bool => $candidate->public_id === $requested) : null;

        return Inertia::render('sites/vehicles/import', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'sources' => $sources->map(fn (Site $candidate): array => ['public_id' => $candidate->public_id, 'name' => $candidate->name])->values()->all(),
            'source' => $source?->public_id,
            'vehicles' => $source === null ? [] : $this->sourceVehicles($source, $site),
            'can' => [
                'copyPrices' => Gate::allows('editPrices', $site),
                'copyBenefits' => Gate::allows('editBenefits', $site),
            ],
            'result' => $request->session()->get('vehicle_import'),
        ]);
    }

    public function store(Request $request, Site $site, SiteVehicleCopier $copier): RedirectResponse
    {
        $this->authorizeDestination($site);

        $data = $request->validate([
            'source' => ['required', 'string', 'size:26'],
            'vehicles' => ['required', 'array', 'min:1', 'max:'.self::MAX_VEHICLES],
            'vehicles.*' => ['string', 'size:26'],
            'include_offers' => ['boolean'],
            'include_benefits' => ['boolean'],
        ], [
            'vehicles.required' => 'Выберите хотя бы один автомобиль.',
            'vehicles.min' => 'Выберите хотя бы один автомобиль.',
        ], ['source' => 'Сайт-источник', 'vehicles' => 'Автомобили']);

        $source = $this->sources($site)->first(fn (Site $candidate): bool => $candidate->public_id === $data['source'])
            ?? throw ValidationException::withMessages(['source' => 'Выберите доступный сайт-источник.']);
        $withOffers = (bool) ($data['include_offers'] ?? false);
        $withBenefits = (bool) ($data['include_benefits'] ?? false);

        if ($withBenefits && ! $withOffers) {
            throw ValidationException::withMessages(['include_benefits' => 'Выгоды копируются вместе с предложениями.']);
        }

        if ($withOffers) {
            Gate::authorize('editPrices', $site);
        }

        if ($withBenefits) {
            Gate::authorize('editBenefits', $site);
        }

        /** @var list<string> $vehicles */
        $vehicles = array_values($data['vehicles']);
        $results = $copier->copy($source, $site, $vehicles, $withOffers, $withBenefits);
        $counts = array_count_values(array_column($results, 'result'));

        $request->session()->flash('vehicle_import', $results);
        Inertia::flash('toast', [
            'type' => ($counts[SiteVehicleCopier::FAILED] ?? 0) > 0 ? 'warning' : 'success',
            'message' => sprintf(
                'Скопировано: %d · Пропущено: %d · Конфликтов: %d · Ошибок: %d',
                $counts[SiteVehicleCopier::COPIED] ?? 0,
                $counts[SiteVehicleCopier::SKIPPED] ?? 0,
                $counts[SiteVehicleCopier::CONFLICT] ?? 0,
                $counts[SiteVehicleCopier::FAILED] ?? 0,
            ),
        ]);

        return to_route('sites.vehicles.imports.create', ['site' => $site, 'source' => $source->public_id]);
    }

    private function authorizeDestination(Site $site): void
    {
        $this->scope->site($site);
        Gate::authorize('editVehicles', $site);
        Gate::authorize('importVehicles', $site);
    }

    /**
     * Other active Sites of the destination's Workspace that the actor may enter and view vehicles of.
     *
     * @return Collection<int, Site>
     */
    private function sources(Site $destination): Collection
    {
        $membership = $this->workspaceContext->membership() ?? abort(403);

        return $this->siteAccess->scopeAccessible(Site::query(), $membership)
            ->where('workspace_id', $destination->workspace_id)
            ->whereKeyNot($destination->getKey())
            ->where('status', SiteStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->filter(fn (Site $candidate): bool => Gate::allows('viewVehicles', $candidate))
            ->values()
            ->toBase();
    }

    /**
     * @return list<array{public_id: string, title: string, subtitle: string|null, status: bool, offers_count: int, benefits_count: int, conflict: bool}>
     */
    private function sourceVehicles(Site $source, Site $destination): array
    {
        $vehicles = $source->vehicles()->ordered()->with('offers.benefits')->get();
        $series = $this->catalog->series($vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_public_id)->values()->all());
        $existing = $destination->vehicles()->pluck('catalog_series_public_id')->flip();

        return array_values($vehicles->map(function (SiteVehicle $vehicle) use ($series, $existing): array {
            $catalog = isset($series[$vehicle->catalog_series_public_id]) ? $this->catalog->seriesTitle($series[$vehicle->catalog_series_public_id]) : null;

            return [
                'public_id' => $vehicle->public_id,
                'title' => $vehicle->custom_name ?? $catalog['title'] ?? 'Модель недоступна',
                'subtitle' => $catalog === null ? null : "{$catalog['generation']} · {$catalog['series']}",
                'status' => $vehicle->status,
                'offers_count' => $vehicle->offers->count(),
                'benefits_count' => (int) $vehicle->offers->sum(fn (SiteOffer $offer): int => $offer->benefits->count()),
                'conflict' => isset($existing[$vehicle->catalog_series_public_id]),
            ];
        })->all());
    }
}
