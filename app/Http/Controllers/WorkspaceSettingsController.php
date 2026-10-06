<?php

namespace App\Http\Controllers;

use App\Enums\WorkspacePermission;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings of the current Workspace only; the Workspace is always taken from the backend context.
 */
class WorkspaceSettingsController extends Controller
{
    public function edit(WorkspaceContext $workspaceContext): Response
    {
        Gate::authorize(WorkspacePermission::EditWorkspace->value);

        $workspace = $this->current($workspaceContext);

        return Inertia::render('workspaces/settings', [
            'settings' => [
                'name' => $workspace->name,
                'plan' => $workspace->plan?->name,
                'created_at' => $workspace->created_at?->toIso8601String(),
            ],
            'nameMaxLength' => Workspace::NAME_MAX_LENGTH,
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, WorkspaceContext $workspaceContext): RedirectResponse
    {
        $workspace = $this->current($workspaceContext);
        $workspace->update(['name' => $request->string('name')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Название пространства сохранено.']);

        return to_route('workspace.settings.edit');
    }

    private function current(WorkspaceContext $workspaceContext): Workspace
    {
        $workspace = $workspaceContext->current();
        abort_if($workspace === null, 403);

        return $workspace;
    }
}
