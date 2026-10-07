<?php

namespace App\Http\Controllers;

use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Enums\WorkspacePermission;
use App\Models\Site;
use App\Models\User;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Support\SiteAccessResolver;
use App\Support\WorkspaceAuthorization;
use App\Support\WorkspaceContext;
use App\Support\WorkspaceEntitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        WorkspaceContext $workspaceContext,
        WorkspaceAuthorization $authorization,
        WorkspaceEntitlements $entitlements,
        SiteAccessResolver $siteAccess,
    ): Response {
        $workspace = $workspaceContext->current();
        $membership = $workspaceContext->membership();
        abort_if($workspace === null, 403);
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $canViewSites = Gate::forUser($user)->allows('viewAny', Site::class);
        $canCreateSites = $authorization->allows($user, WorkspacePermission::CreateSites);
        $activeSiteLimit = $entitlements->limit($workspace, Entitlement::MaxSites);
        $activeSiteCount = $workspace->sites()
            ->where('status', SiteStatus::Active->value)
            ->count();

        return Inertia::render('dashboard', [
            'currentWorkspace' => [
                'public_id' => $workspace->public_id,
                'name' => $workspace->name,
            ],
            'sites' => $canViewSites && $membership !== null
                ? $siteAccess->scopeAccessible(Site::query(), $membership)
                    ->orderBy('name')
                    ->get(['id', 'workspace_id', 'public_id', 'name', 'status', 'subdomain', 'active_published_version_id'])
                    ->each(fn (Site $site) => $site->setRelation('workspace', $workspace))
                    ->map(fn (Site $site): array => [
                        'public_id' => $site->public_id,
                        'name' => $site->name,
                        'status' => $site->status->value,
                        'address' => $site->active_published_version_id !== null && $site->status === SiteStatus::Active
                            ? PublicSiteResolver::primaryUrl($site)
                            : null,
                    ])
                    ->values()
                    ->all()
                : [],
            'canViewSites' => $canViewSites,
            'canViewVehicles' => array_intersect(
                [WorkspacePermission::ViewVehicles->value, WorkspacePermission::EditVehicles->value, WorkspacePermission::EditPrices->value, WorkspacePermission::EditBenefits->value],
                $authorization->permissionKeys($user),
            ) !== [],
            'canCreateSites' => $canCreateSites,
            'siteLimit' => [
                'active' => $activeSiteCount,
                'max' => $activeSiteLimit,
                'reached' => $activeSiteCount >= $activeSiteLimit,
            ],
        ]);
    }
}
