<?php

namespace App\Http\Controllers;

use App\Actions\Sites\CreateSite;
use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
use App\Exceptions\SiteCreationRejectedException;
use App\Exceptions\SiteLimitReachedException;
use App\Http\Requests\StoreSiteRequest;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use App\Models\Template;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Support\DesignerScope;
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
            'siteTypes' => array_map(function (SiteType $type) use ($entitlements, $workspace): array {
                $required = $type->requiredEntitlement();

                return [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'allowed' => $required === null || $entitlements->allows($workspace, $required),
                    'blank_allowed' => $type->allowsBlankStart(),
                ];
            }, SiteType::cases()),
            'templates' => Template::query()
                ->availableForSites()
                ->orderBy('name')
                ->get(['public_id', 'name', 'site_types'])
                ->filter(fn (Template $template): bool => $template->siteTypes() !== [])
                ->map(fn (Template $template): array => [
                    'public_id' => $template->public_id,
                    'name' => $template->name,
                    'site_types' => array_map(fn (SiteType $type): string => $type->value, $template->siteTypes()),
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

        $template = $request->input('start') === StoreSiteRequest::START_TEMPLATE
            ? Template::query()->availableForSites()->where('public_id', $request->string('template')->toString())->firstOrFail()
            : null;

        try {
            $site = $createSite->create(
                $workspace,
                $request->string('name')->toString(),
                $request->enum('site_type', SiteType::class) ?? SiteType::MultiPage,
                $template,
            );
        } catch (SiteCreationRejectedException $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        } catch (SiteLimitReachedException) {
            throw ValidationException::withMessages([
                'site' => 'Достигнут лимит активных сайтов для текущего рабочего пространства.',
            ]);
        }

        return to_route('dashboard', ['site' => $site->public_id]);
    }

    public function show(Site $site, DesignerScope $scope): Response
    {
        $scope->site($site);
        Gate::authorize('view', $site);

        $active = $site->activePublishedVersion()->first(['id', 'version_number', 'ready_at']);

        return Inertia::render('sites/show', [
            'site' => [
                'public_id' => $site->public_id,
                'name' => $site->name,
                'status' => $site->status->value,
                'subdomain' => $site->subdomain,
                'address' => PublicSiteResolver::primaryUrl($site),
                'created_at' => $site->created_at?->toIso8601String(),
            ],
            'production' => $active === null ? null : [
                'version_number' => $active->version_number,
                'published_at' => $active->ready_at?->toIso8601String(),
            ],
            'can' => [
                'update' => Gate::allows('update', $site),
                'publish' => Gate::allows('publish', $site),
                'preview' => Gate::allows('preview', $site),
            ],
        ]);
    }

    public function update(UpdateSiteRequest $request, Site $site): RedirectResponse
    {
        $site->update(['name' => $request->string('name')->toString()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Название сайта сохранено.']);

        return to_route('sites.show', $site);
    }
}
