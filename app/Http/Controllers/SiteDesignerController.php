<?php

namespace App\Http\Controllers;

use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteDesignerController extends Controller
{
    public function __invoke(Request $request, Site $site, DesignerScope $scope): Response
    {
        $scope->site($site);
        Gate::authorize('view', $site);

        $pages = $site->pages()->orderBy('sort_order')->orderBy('id')->get();
        $page = $request->filled('page')
            ? $pages->firstWhere('public_id', $request->string('page')->toString())
            : $pages->firstWhere('is_home', true);
        abort_if($page === null, 404);

        $blocks = $page->blocks()->with('version.definition')->get();

        return Inertia::render('sites/designer', [
            'site' => [
                'public_id' => $site->public_id,
                'name' => $site->name,
            ],
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
                    'state' => (object) $block->state_json,
                ])
                ->values()
                ->all(),
            'selectedBlock' => $blocks->firstWhere('public_id', $request->query('block'))?->public_id,
            'library' => BlockDefinition::query()
                ->where('is_official', true)
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
                'editContent' => Gate::allows('editContent', $site),
            ],
        ]);
    }
}
