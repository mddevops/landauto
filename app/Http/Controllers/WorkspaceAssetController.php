<?php

namespace App\Http\Controllers;

use App\Assets\WorkspaceAssets;
use App\Enums\SiteStatus;
use App\Enums\WorkspacePermission;
use App\Http\Requests\UploadWorkspaceAssetRequest;
use App\Models\Site;
use App\Models\Workspace;
use App\Models\WorkspaceAsset;
use App\Support\SiteAccessResolver;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Медиатека» of the current Workspace. Files are private; only public IDs and app URLs reach
 * the browser, never storage paths.
 */
class WorkspaceAssetController extends Controller
{
    private const SHOWN = 200;

    public function __construct(
        private WorkspaceContext $workspaceContext,
        private SiteAccessResolver $siteAccess,
    ) {}

    public function index(): Response
    {
        $workspace = $this->authorizeLibrary();
        $total = $workspace->assets()->count();

        return Inertia::render('workspaces/assets/index', [
            'assets' => $workspace->assets()
                ->latest('id')
                ->limit(self::SHOWN)
                ->get()
                ->map(fn (WorkspaceAsset $asset): array => [
                    'public_id' => $asset->public_id,
                    'original_name' => $asset->original_name,
                    'mime_type' => $asset->mime_type,
                    'size_bytes' => $asset->size_bytes,
                    'width' => $asset->width,
                    'height' => $asset->height,
                    'url' => route('workspace.assets.show', $asset->public_id, false),
                ])->values()->all(),
            'total' => $total,
            'shownLimit' => self::SHOWN,
            'sites' => $this->destinationSites()
                ->map(fn (Site $site): array => ['public_id' => $site->public_id, 'name' => $site->name])
                ->values()->all(),
        ]);
    }

    public function store(UploadWorkspaceAssetRequest $request, WorkspaceAssets $assets): RedirectResponse
    {
        $workspace = $this->workspaceContext->current() ?? abort(403);
        $file = $request->file('file');
        abort_if($file === null, 422);

        $assets->upload($workspace, $file);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изображение загружено.']);

        return back();
    }

    public function show(string $asset): StreamedResponse
    {
        $libraryAsset = $this->asset($this->authorizeLibrary(), $asset);

        return Storage::disk(WorkspaceAsset::DISK)->response(
            $libraryAsset->path,
            basename($libraryAsset->path),
            [
                'Content-Type' => $libraryAsset->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=31536000, immutable',
            ],
        );
    }

    public function destroy(string $asset, WorkspaceAssets $assets): RedirectResponse
    {
        $assets->delete($this->asset($this->authorizeLibrary(), $asset));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Изображение удалено из медиатеки. Копии на сайтах не изменились.']);

        return back();
    }

    /**
     * «Копировать на сайт»: requires the Workspace media permission, access to the destination
     * Site and manage_assets there. The Site receives an independent file copy.
     */
    public function copyToSite(Request $request, string $asset, WorkspaceAssets $assets): RedirectResponse
    {
        $libraryAsset = $this->asset($this->authorizeLibrary(), $asset);
        $sitePublicId = $request->string('site')->toString();
        $site = $this->destinationSites()->first(fn (Site $site): bool => $site->public_id === $sitePublicId)
            ?? throw ValidationException::withMessages(['site' => 'Выберите доступный сайт.']);

        $assets->copyToSite($libraryAsset, $site);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Изображение скопировано на сайт «{$site->name}»."]);

        return back();
    }

    private function authorizeLibrary(): Workspace
    {
        Gate::authorize(WorkspacePermission::ManageWorkspaceAssets->value);

        return $this->workspaceContext->current() ?? abort(403);
    }

    private function asset(Workspace $workspace, string $publicId): WorkspaceAsset
    {
        return $workspace->assets()->where('public_id', $publicId)->first() ?? abort(404);
    }

    /**
     * Active Sites of the current Workspace the actor may enter and manage assets of.
     *
     * @return Collection<int, Site>
     */
    private function destinationSites(): Collection
    {
        $membership = $this->workspaceContext->membership() ?? abort(403);

        return $this->siteAccess->scopeAccessible(Site::query(), $membership)
            ->where('status', SiteStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->filter(fn (Site $site): bool => Gate::allows('manageAssets', $site))
            ->values()
            ->toBase();
    }
}
