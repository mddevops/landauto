<?php

namespace App\Http\Controllers\Vehicles;

use App\Automotive\SiteVehicleCopier;
use App\Automotive\VehicleCatalog;
use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoEquipment;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Support\DesignerScope;
use App\Support\Money;
use App\Support\SiteAccessResolver;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
            'conflicts' => ['array', 'max:'.self::MAX_VEHICLES],
            'conflicts.*' => ['array:mode,fields'],
            'conflicts.*.mode' => ['required', Rule::in(['skip', 'update'])],
            'conflicts.*.fields' => ['array'],
            'conflicts.*.fields.*' => [Rule::in(SiteVehicleCopier::FIELDS)],
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

        /** @var list<string> $vehicles */
        $vehicles = array_values($data['vehicles']);
        $updates = $this->conflictUpdates($data['conflicts'] ?? [], $vehicles);
        $updatedFields = array_merge([], ...array_values($updates));

        if ($withOffers || in_array(SiteVehicleCopier::FIELD_OFFERS, $updatedFields, true)) {
            Gate::authorize('editPrices', $site);
        }

        if ($withBenefits || in_array(SiteVehicleCopier::FIELD_BENEFITS, $updatedFields, true)) {
            Gate::authorize('editBenefits', $site);
        }

        $results = $copier->copy($source, $site, $vehicles, $withOffers, $withBenefits, $updates);
        $counts = array_count_values(array_column($results, 'result'));
        $failed = $counts[SiteVehicleCopier::FAILED] ?? 0;
        $ambiguous = $counts[SiteVehicleCopier::AMBIGUOUS] ?? 0;

        $request->session()->flash('vehicle_import', $results);
        Inertia::flash('toast', [
            'type' => $failed + $ambiguous > 0 ? 'warning' : 'success',
            'message' => sprintf(
                'Скопировано: %d · Обновлено: %d · Пропущено: %d · Конфликтов не решено: %d',
                $counts[SiteVehicleCopier::COPIED] ?? 0,
                $counts[SiteVehicleCopier::UPDATED] ?? 0,
                $counts[SiteVehicleCopier::SKIPPED] ?? 0,
                $counts[SiteVehicleCopier::CONFLICT] ?? 0,
            ).($ambiguous > 0 ? " · Не обновлено из-за дубликатов предложений: {$ambiguous}" : '')
                .($failed > 0 ? " · Ошибок: {$failed}" : ''),
        ]);

        return to_route('sites.vehicles.imports.create', ['site' => $site, 'source' => $source->public_id]);
    }

    /**
     * Explicit "update selected fields" choices for selected conflicting vehicles; anything else
     * (no choice, «Пропустить», no fields) keeps the default skip.
     *
     * @param  array<array-key, mixed>  $conflicts
     * @param  list<string>  $vehicles
     * @return array<string, list<string>>
     */
    private function conflictUpdates(array $conflicts, array $vehicles): array
    {
        $updates = [];

        foreach ($conflicts as $publicId => $choice) {
            if (! is_string($publicId) || ! in_array($publicId, $vehicles, true) || ! is_array($choice) || ($choice['mode'] ?? null) !== 'update') {
                continue;
            }

            $fields = array_values(array_intersect(SiteVehicleCopier::FIELDS, is_array($choice['fields'] ?? null) ? $choice['fields'] : []));

            if ($fields === []) {
                throw ValidationException::withMessages(["conflicts.{$publicId}.fields" => 'Выберите, что обновить, или пропустите автомобиль.']);
            }

            $updates[$publicId] = $fields;
        }

        return $updates;
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
     * Source vehicles with a read-only preview of each conflict (same Series already on the
     * destination). Nothing is changed here.
     *
     * @return list<array<string, mixed>>
     */
    private function sourceVehicles(Site $source, Site $destination): array
    {
        $vehicles = $source->vehicles()->ordered()->with(['offers' => fn ($query) => $query->ordered()->with('benefits'), 'mediaSets'])->get();
        $series = $this->catalog->series($vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_public_id)->values()->all());
        $existing = $destination->vehicles()
            ->whereIn('catalog_series_public_id', $vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_public_id)->all())
            ->with(['offers' => fn ($query) => $query->ordered()->with('benefits'), 'mediaSets'])
            ->get()
            ->keyBy('catalog_series_public_id');
        $equipments = $this->catalog->equipments($vehicles->flatMap(fn (SiteVehicle $vehicle) => $vehicle->offers->pluck('catalog_equipment_public_id'))->unique()->values()->all());

        return array_values($vehicles->map(function (SiteVehicle $vehicle) use ($series, $existing, $equipments): array {
            $catalog = isset($series[$vehicle->catalog_series_public_id]) ? $this->catalog->seriesTitle($series[$vehicle->catalog_series_public_id]) : null;
            $target = $existing->get($vehicle->catalog_series_public_id);

            return [
                'public_id' => $vehicle->public_id,
                'title' => $vehicle->custom_name ?? $catalog['title'] ?? 'Модель недоступна',
                'subtitle' => $catalog === null ? null : "{$catalog['generation']} · {$catalog['series']}",
                'status' => $vehicle->status,
                'offers_count' => $vehicle->offers->count(),
                'benefits_count' => (int) $vehicle->offers->sum(fn (SiteOffer $offer): int => $offer->benefits->count()),
                'conflict' => $target !== null,
                'existing' => $target === null ? null : $this->conflictPreview($vehicle, $target, $catalog['title'] ?? 'Модель недоступна', $equipments),
            ];
        })->all());
    }

    /**
     * @param  Collection<string, AutoEquipment>  $equipments
     * @return array<string, mixed>
     */
    private function conflictPreview(SiteVehicle $source, SiteVehicle $target, string $catalogTitle, Collection $equipments): array
    {
        $grouped = $target->offers->groupBy('catalog_equipment_public_id');
        $targetOffers = $grouped->filter(fn ($offers): bool => $offers->count() === 1)->map(fn ($offers) => $offers->first());
        $sourceEquipment = $source->offers->pluck('catalog_equipment_public_id')->flip();
        $duplicates = $grouped->filter(fn ($offers, $equipment): bool => $offers->count() > 1 && $sourceEquipment->has($equipment))->count();
        $matched = $source->offers->filter(fn (SiteOffer $offer): bool => $targetOffers->has($offer->catalog_equipment_public_id));

        return [
            'public_id' => $target->public_id,
            'title' => $target->custom_name ?? $catalogTitle,
            'text_differs' => [$source->custom_name, $source->custom_description] !== [$target->custom_name, $target->custom_description],
            'media_differs' => $source->mediaSets->pluck('public_id')->all() !== $target->mediaSets->pluck('public_id')->all(),
            'status_differs' => $source->status !== $target->status,
            'offers' => [
                'matched' => $matched->count(),
                'missing' => $source->offers->filter(fn (SiteOffer $offer): bool => ! $grouped->has($offer->catalog_equipment_public_id))->count(),
                'duplicate_equipment' => $duplicates,
                'destination_only' => $target->offers->filter(fn (SiteOffer $offer): bool => ! $sourceEquipment->has($offer->catalog_equipment_public_id))->count(),
                'price_changes' => array_values($matched
                    ->filter(fn (SiteOffer $offer): bool => $offer->price_minor !== $targetOffers[$offer->catalog_equipment_public_id]->price_minor)
                    ->take(10)
                    ->map(fn (SiteOffer $offer): array => [
                        'equipment' => $equipments->get($offer->catalog_equipment_public_id)->name ?? 'Комплектация',
                        'source' => Money::format($offer->price_minor, $offer->currency),
                        'destination' => Money::format($targetOffers[$offer->catalog_equipment_public_id]->price_minor, $targetOffers[$offer->catalog_equipment_public_id]->currency),
                    ])
                    ->all()),
            ],
        ];
    }
}
