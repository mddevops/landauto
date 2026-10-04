<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\PlatformAuthorization;
use App\Support\WorkspaceAuthorization;
use App\Support\WorkspaceContext;
use Illuminate\Http\Request;
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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
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
