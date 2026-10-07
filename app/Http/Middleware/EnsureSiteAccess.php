<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Support\SiteAccessResolver;
use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the whole `sites/{site}` route group: a Site of another Workspace, or one the member is
 * not assigned to, is reported as missing before any controller or nested resource runs.
 */
class EnsureSiteAccess
{
    public function __construct(
        private WorkspaceContext $workspaceContext,
        private SiteAccessResolver $siteAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $site = $request->route('site');

        abort_unless(
            $site instanceof Site
                && $site->workspace_id === $this->workspaceContext->current()?->id
                && $this->siteAccess->canAccess($this->workspaceContext->membership(), $site),
            404,
        );

        return $next($request);
    }
}
