<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Models\Workspace;
use App\Support\PlatformAuthorization;
use App\Support\WorkspaceAuthorization;
use App\Support\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private WorkspaceContext $workspaceContext,
        private WorkspaceAuthorization $workspaceAuthorization,
        private PlatformAuthorization $platformAuthorization,
    ) {}

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn () => $request->user()?->only([
                    'name',
                    'email',
                    'email_verified_at',
                ]),
            ],
            'workspace' => fn () => [
                'current' => $this->workspaceSummary($this->workspaceContext->current()),
                'available' => $this->workspaceContext->available()
                    ->map(fn (Workspace $workspace): array => $this->workspaceSummary($workspace))
                    ->values()
                    ->all(),
                'permissions' => $request->user() === null
                    ? []
                    : $this->workspaceAuthorization->permissionKeys($request->user()),
            ],
            'platform' => fn () => [
                'permissions' => $request->user() === null
                    ? []
                    : $this->platformAuthorization->permissionKeys($request->user()),
            ],
            'siteContext' => fn () => $this->siteContext($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Site shell navigation for routes bound to a Site of the current Workspace. Visibility is a UX
     * hint only; every Site page still authorizes on its own.
     *
     * @return array{public_id: string, name: string, can: array<string, bool>}|null
     */
    private function siteContext(Request $request): ?array
    {
        $site = $request->route('site');
        $user = $request->user();

        if (! $site instanceof Site || $user === null || $site->workspace_id !== $this->workspaceContext->current()?->id) {
            return null;
        }

        $gate = Gate::forUser($user);

        if ($gate->denies('view', $site)) {
            return null;
        }

        return [
            'public_id' => $site->public_id,
            'name' => $site->name,
            'can' => [
                'preview' => $gate->allows('preview', $site),
                'viewVehicles' => $gate->allows('viewVehicles', $site),
                'viewSubmissions' => $gate->allows('viewSubmissions', $site),
                'viewDeliveryLogs' => $gate->allows('viewDeliveryLogs', $site),
                'viewIntegrations' => $gate->allows('viewIntegrations', $site),
                'editForms' => $gate->allows('editForms', $site),
                'publish' => $gate->allows('publish', $site),
                'manageDomains' => $gate->allows('manageDomains', $site),
            ],
        ];
    }

    /**
     * @return array{public_id: string, name: string}|null
     */
    private function workspaceSummary(?Workspace $workspace): ?array
    {
        return $workspace === null ? null : [
            'public_id' => $workspace->public_id,
            'name' => $workspace->name,
        ];
    }
}
