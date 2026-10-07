<?php

namespace App\Http\Controllers;

use App\Automotive\VehicleBindings;
use App\Blocks\BlockReferenceInspector;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Popups\PopupRuntime;
use App\Support\DesignerScope;
use App\Support\SiteDesignTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteDesignerController extends Controller
{
    public function __invoke(Request $request, Site $site, DesignerScope $scope, VehicleBindings $vehicleBindings, PopupRuntime $popups, BlockReferenceInspector $references): Response
    {
        $scope->site($site);
        Gate::authorize('view', $site);

        $pages = $site->pages()->orderBy('sort_order')->orderBy('id')->get();
        $page = $request->filled('page')
            ? $pages->firstWhere('public_id', $request->string('page')->toString())
            : $pages->firstWhere('is_home', true);
        abort_if($page === null, 404);

        $blocks = $page->blocks()->with('version.definition')->get();
        $vehicles = $vehicleBindings->forSite($site);

        return Inertia::render('sites/designer', [
            'site' => [
                'public_id' => $site->public_id,
                'name' => $site->name,
            ],
            'design' => SiteDesignTokens::resolve($site->design_tokens),
            'page' => [
                'public_id' => $page->public_id,
                'title' => $page->title,
            ],
            'pages' => $pages
                ->map(fn (Page $sitePage): array => [
                    'public_id' => $sitePage->public_id,
                    'title' => $sitePage->title,
                    'slug' => $sitePage->slug,
                    'is_home' => $sitePage->is_home,
                    'seo' => [
                        'title' => $sitePage->seo_title,
                        'description' => $sitePage->seo_description,
                        'noindex' => $sitePage->seo_noindex,
                    ],
                ])
                ->values()
                ->all(),
            'blocks' => $blocks
                ->map(fn (BlockInstance $block): array => [
                    'public_id' => $block->public_id,
                    'slug' => $block->version->definition->slug,
                    'name' => $block->version->definition->name,
                    'version' => $block->version->version,
                    'is_hidden' => $block->is_hidden,
                    'schema' => $block->version->schema_json,
                    'sandbox' => $block->version->sandboxSource(),
                    'state' => (object) $block->state_json,
                ])
                ->values()
                ->all(),
            'assets' => $site->assets()
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (SiteAsset $asset): array => [
                    'public_id' => $asset->public_id,
                    'name' => $asset->original_name,
                    'url' => route('sites.assets.show', [$site, $asset], false),
                    'width' => $asset->width,
                    'height' => $asset->height,
                ])
                ->values()
                ->all(),
            'vehicles' => $vehicles,
            'popups' => $popups->forSite($site),
            'referenceIssues' => (object) $references->inspectPage($page, $blocks),
            'selectedBlock' => $blocks->firstWhere('public_id', $request->query('block'))?->public_id,
            'library' => BlockDefinition::query()
                ->platformOwned()
                ->whereHas('versions')
                ->orderBy('id')
                ->get(['slug', 'name'])
                ->map(fn (BlockDefinition $definition): array => [
                    'slug' => $definition->slug,
                    'name' => $definition->name,
                ])
                ->values()
                ->all(),
            'can' => [
                'editDesign' => Gate::allows('editDesign', $site),
                'editStructure' => Gate::allows('editStructure', $site),
                'addPage' => Gate::allows('addPage', $site),
                'editContent' => Gate::allows('editContent', $site),
                'manageAssets' => Gate::allows('manageAssets', $site),
                'preview' => Gate::allows('preview', $site),
                'viewSubmissions' => Gate::allows('viewSubmissions', $site),
                'viewIntegrations' => Gate::allows('viewIntegrations', $site),
                'viewDeliveryLogs' => Gate::allows('viewDeliveryLogs', $site),
                'editSeo' => Gate::allows('editSeo', $site),
                'editSeoIndexing' => Gate::allows('editSeoIndexing', $site),
            ],
        ]);
    }
}
