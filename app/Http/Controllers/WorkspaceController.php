<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Additional Workspaces for the signed-in, verified user (D-110). There is no workspace cap;
 * the creator becomes Owner and the new Workspace becomes current.
 */
class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('workspaces/create', [
            'nameMaxLength' => Workspace::NAME_MAX_LENGTH,
        ]);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace, WorkspaceContext $workspaceContext): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $workspace = $createWorkspace->create($user, $request->string('name')->toString());

        $workspaceContext->resolve($user, $request->session());
        $workspaceContext->switchTo($workspace->public_id, $request->session());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Пространство создано.']);

        return to_route('dashboard');
    }
}
