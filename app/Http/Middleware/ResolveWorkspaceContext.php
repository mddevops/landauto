<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspaceContext
{
    public function __construct(private WorkspaceContext $workspaceContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $this->workspaceContext->resolve($request->user(), $request->session());
        }

        return $next($request);
    }
}
