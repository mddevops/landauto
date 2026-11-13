<?php

namespace App\Http\Controllers\Vehicles;

use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Models\VehicleCollection;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Customer collections («Подборки») of the current Workspace's Catalog vehicles (ADR-010 §8).
 */
class VehicleCollectionController extends Controller
{
    public function __construct(private WorkspaceContext $workspaceContext) {}

    public function store(Request $request): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $data = $this->validated($request, $workspace);

        $collection = new VehicleCollection([
            'name' => $data['name'],
            'sort_order' => ((int) $workspace->vehicleCollections()->max('sort_order')) + 1,
        ]);
        $collection->workspace()->associate($workspace)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Подборка «{$collection->name}» создана."]);

        return back();
    }

    public function update(Request $request, string $collection): RedirectResponse
    {
        $workspace = $this->authorizeLibrary();
        $found = $this->collection($workspace, $collection);
        $found->update($this->validated($request, $workspace, $found));

        return back();
    }

    public function destroy(string $collection): RedirectResponse
    {
        $this->collection($this->authorizeLibrary(), $collection)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Подборка удалена. Автомобили остались в Каталоге.']);

        return back();
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, Workspace $workspace, ?VehicleCollection $ignore = null): array
    {
        $request->merge(['name' => trim($request->string('name')->toString())]);

        /** @var array{name: string} */
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('vehicle_collections', 'name')->where('workspace_id', $workspace->id)->ignore($ignore?->id),
            ],
        ], ['name.unique' => 'Подборка с таким названием уже есть.'], ['name' => 'Название подборки']);
    }

    private function authorizeLibrary(): Workspace
    {
        Gate::authorize(WorkspacePermission::ManageWorkspaceVehicleLibrary->value);

        return $this->workspaceContext->current() ?? abort(403);
    }

    private function collection(Workspace $workspace, string $publicId): VehicleCollection
    {
        return $workspace->vehicleCollections()->where('public_id', $publicId)->first() ?? abort(404);
    }
}
