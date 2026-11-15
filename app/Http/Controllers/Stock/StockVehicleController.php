<?php

namespace App\Http\Controllers\Stock;

use App\Catalog\CatalogPresenter;
use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockVehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StoreStockVehicleRequest;
use App\Http\Requests\Stock\UpdateStockVehicleRequest;
use App\Models\StockVehicle;
use App\Stock\StockAccess;
use App\Stock\StockListingCreator;
use App\Stock\StockPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Workspace «Склад» cabinet (ADR-012).
 */
class StockVehicleController extends Controller
{
    public function __construct(
        private StockAccess $stock,
        private StockPresenter $presenter,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->stock->authorize($request->user());
        $condition = $request->string('condition')->toString();
        $status = $request->string('status')->toString();
        $availability = $request->string('availability')->toString();
        $q = trim($request->string('q')->toString());

        $vehicles = $workspace->stockVehicles()
            ->with('photos')
            ->ordered()
            ->when(
                StockCondition::tryFrom($condition) !== null,
                fn ($query) => $query->where('condition', $condition),
            )
            ->when(
                StockVehicleStatus::tryFrom($status) !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                StockAvailability::tryFrom($availability) !== null,
                fn ($query) => $query->where('availability', $availability),
            )
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('inner_id', 'like', $like)
                        ->orWhere('stock_number', 'like', $like)
                        ->orWhere('color_name', 'like', $like)
                        ->orWhere('body_name', 'like', $like);
                });
            })
            ->get()
            ->map(fn (StockVehicle $vehicle): array => $this->presenter->card($vehicle))
            ->values()
            ->all();

        return Inertia::render('workspaces/stock/index', [
            'vehicles' => $vehicles,
            'filters' => [
                'condition' => StockCondition::tryFrom($condition)?->value,
                'status' => StockVehicleStatus::tryFrom($status)?->value,
                'availability' => StockAvailability::tryFrom($availability)?->value,
                'q' => $q === '' ? null : $q,
            ],
            'filter_options' => [
                'conditions' => array_map(fn (StockCondition $value): array => [
                    'value' => $value->value,
                    'label' => $value->label(),
                ], StockCondition::cases()),
                'statuses' => array_map(fn (StockVehicleStatus $value): array => [
                    'value' => $value->value,
                    'label' => $value->label(),
                ], StockVehicleStatus::cases()),
                'availabilities' => array_map(fn (StockAvailability $value): array => [
                    'value' => $value->value,
                    'label' => $value->label(),
                ], StockAvailability::cases()),
            ],
        ]);
    }

    public function create(Request $request, CatalogPresenter $catalog): Response
    {
        $this->stock->authorize($request->user());

        return Inertia::render('workspaces/stock/create', $catalog->wizard($request));
    }

    public function store(StoreStockVehicleRequest $request, StockListingCreator $creator): RedirectResponse
    {
        $workspace = $this->stock->authorize($request->user());
        $vehicle = $creator->create($workspace, $request->draft());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $vehicle->status === StockVehicleStatus::Draft
                ? 'Черновик сохранён на складе. Добавьте фото, когда будете готовы.'
                : 'Автомобиль добавлен на склад.',
        ]);

        return to_route('workspace.stock.show', $vehicle);
    }

    public function show(Request $request, StockVehicle $vehicle): Response
    {
        $workspace = $this->stock->authorize($request->user());
        abort_unless($vehicle->workspace_id === $workspace->id, 404);
        $vehicle->load('photos');

        return Inertia::render('workspaces/stock/show', [
            'vehicle' => $this->presenter->detail($vehicle),
            'status_options' => array_map(fn (StockVehicleStatus $value): array => [
                'value' => $value->value,
                'label' => $value->label(),
            ], StockVehicleStatus::cases()),
            'availability_options' => array_map(fn (StockAvailability $value): array => [
                'value' => $value->value,
                'label' => $value->label(),
            ], StockAvailability::cases()),
        ]);
    }

    public function update(UpdateStockVehicleRequest $request, StockVehicle $vehicle): RedirectResponse
    {
        $workspace = $this->stock->authorize($request->user());
        abort_unless($vehicle->workspace_id === $workspace->id, 404);

        $vehicle->fill($request->payload())->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изменения сохранены.']);

        return back();
    }

    public function archive(Request $request, StockVehicle $vehicle): RedirectResponse
    {
        $workspace = $this->stock->authorize($request->user());
        abort_unless($vehicle->workspace_id === $workspace->id, 404);

        $vehicle->forceFill(['status' => StockVehicleStatus::Archived])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль перемещён в архив.']);

        return to_route('workspace.stock.index', ['status' => StockVehicleStatus::Archived->value]);
    }

    public function restore(Request $request, StockVehicle $vehicle): RedirectResponse
    {
        $workspace = $this->stock->authorize($request->user());
        abort_unless($vehicle->workspace_id === $workspace->id, 404);

        $status = $vehicle->condition === StockCondition::Used && $vehicle->mileage_km === null
            ? StockVehicleStatus::Draft
            : StockVehicleStatus::Ready;

        $vehicle->forceFill(['status' => $status])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Автомобиль восстановлен из архива.']);

        return to_route('workspace.stock.show', $vehicle);
    }
}
