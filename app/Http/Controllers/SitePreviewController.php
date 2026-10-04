<?php

namespace App\Http\Controllers;

use App\Automotive\VehicleBindings;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Support\DesignerScope;
use App\Support\SiteDesignTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated preview of the current draft. It renders draft state only and never creates
 * or changes Published output.
 */
class SitePreviewController extends Controller
{
    public function __invoke(Request $request, Site $site, DesignerScope $scope, VehicleBindings $vehicleBindings): Response
    {
        $scope->site($site);
        Gate::authorize('preview', $site);

        $pages = $site->pages()->orderBy('sort_order')->orderBy('id')->get();
        $page = $request->filled('page')
            ? $pages->firstWhere('public_id', $request->string('page')->toString())
            : $pages->firstWhere('is_home', true);
        abort_if($page === null, 404);

        return Inertia::render('sites/preview', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'page' => ['public_id' => $page->public_id, 'title' => $page->title],
            'pages' => $pages
                ->map(fn (Page $sitePage): array => ['public_id' => $sitePage->public_id, 'title' => $sitePage->title])
                ->values()
                ->all(),
            'design' => SiteDesignTokens::resolve($site->design_tokens),
            'blocks' => $page->blocks()
                ->where('is_hidden', false)
                ->with('version.definition')
                ->get()
                ->map(fn (BlockInstance $block): array => [
                    'public_id' => $block->public_id,
                    'slug' => $block->version->definition->slug,
                    'name' => $block->version->definition->name,
                    'state' => (object) $block->state_json,
                ])
                ->values()
                ->all(),
            'assets' => $site->assets()
                ->get()
                ->map(fn (SiteAsset $asset): array => [
                    'public_id' => $asset->public_id,
                    'url' => route('sites.assets.show', [$site, $asset], false),
                ])
                ->values()
                ->all(),
            'vehicles' => $vehicleBindings->forSite($site),
        ]);
    }
}
