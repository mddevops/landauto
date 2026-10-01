<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceContextController extends Controller
{
    public function update(Request $request, string $workspace, WorkspaceContext $workspaceContext): RedirectResponse
    {
        $workspaceContext->switchTo($workspace, $request->session());

        return to_route('dashboard');
    }
}
