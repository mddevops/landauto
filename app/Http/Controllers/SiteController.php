<?php

namespace App\Http\Controllers;

use App\Actions\Sites\CreateSite;
use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Exceptions\SiteLimitReachedException;
use App\Http\Requests\StoreSiteRequest;
use App\Models\Site;
use App\Models\Template;
use App\Support\WorkspaceContext;
use App\Support\WorkspaceEntitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function create(
        WorkspaceContext $workspaceContext,
        WorkspaceEntitlements $entitlements,
    ): Response {
        Gate::authorize('create', Site::class);

        $workspace = $workspaceContext->current();
        abort_if($workspace === null, 403);

        $activeSiteLimit = $entitlements->limit($workspace, Entitlement::MaxSites);
        $activeSiteCount = $workspace->sites()
            ->where('status', SiteStatus::Active->value)
            ->count();

        return Inertia::render('sites/create', [
            'currentWorkspace' => [
                'public_id' => $workspace->public_id,
                'name' => $workspace->name,
            ],
            'templates' => Template::query()
                ->where('is_official', true)
                ->orderBy('name')
                ->get(['public_id', 'name'])
                ->map(fn (Template $template): array => [
                    'public_id' => $template->public_id,
                    'name' => $template->name,
                ])
                ->values()
                ->all(),
            'siteLimit' => [
                'active' => $activeSiteCount,
                'max' => $activeSiteLimit,
                'reached' => $activeSiteCount >= $activeSiteLimit,
            ],
        ]);
    }

    public function store(
        StoreSiteRequest $request,
        WorkspaceContext $workspaceContext,
        CreateSite $createSite,
    ): RedirectResponse {
        $workspace = $workspaceContext->current();
        abort_if($workspace === null, 403);

        $template = Template::query()
            ->where('public_id', $request->string('template')->toString())
            ->where('is_official', true)
            ->firstOrFail();

        try {
            $site = $createSite->create(
                $workspace,
                $template,
                $request->string('name')->toString(),
            );
        } catch (SiteLimitReachedException) {
            throw ValidationException::withMessages([
                'site' => 'Достигнут лимит активных сайтов для текущего рабочего пространства.',
            ]);
        }

        return to_route('dashboard', ['site' => $site->public_id]);
    }
}
