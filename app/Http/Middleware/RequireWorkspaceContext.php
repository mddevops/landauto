<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkspaceContext
{
    public function __construct(private WorkspaceContext $workspaceContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->workspaceContext->current() === null) {
            return new RedirectResponse(route('home'));
        }

        return $next($request);
    }
}
