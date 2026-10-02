<?php

namespace App\Http\Controllers;

use App\Models\BlockInstance;
use App\Models\Site;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteDesignerController extends Controller
{
    public function __invoke(Site $site, WorkspaceContext $workspaceContext): Response
    {
        // Sites are addressed only within the current Workspace; others must not be confirmed.
        abort_unless($site->workspace_id === $workspaceContext->current()?->id, 404);

        Gate::authorize('view', $site);

        $page = $site->homePage()->first();
        $blocks = $page?->blocks()->with('version.definition')->get() ?? collect();

        return Inertia::render('sites/designer', [
            'site' => [
                'public_id' => $site->public_id,
                'name' => $site->name,
            ],
            'page' => $page === null ? null : [
                'public_id' => $page->public_id,
                'title' => $page->title,
            ],
            'blocks' => $blocks
                ->map(fn (BlockInstance $block): array => [
                    'public_id' => $block->public_id,
                    'slug' => $block->version->definition->slug,
                    'name' => $block->version->definition->name,
                    'version' => $block->version->version,
                    'state' => (object) $block->state_json,
                ])
                ->values()
                ->all(),
        ]);
    }
}
