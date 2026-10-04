<?php

namespace App\Http\Controllers;

use App\Actions\Designer\ArrangePageBlocks;
use App\Blocks\BlockStateDefaults;
use App\Exceptions\InvalidBlockStateException;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageBlockController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private ArrangePageBlocks $arrange,
    ) {}

    public function store(Request $request, Site $site, Page $page, BlockStateDefaults $defaults): RedirectResponse
    {
        $this->scope->page($site, $page);
        Gate::authorize('editDesign', $site);
        $validated = $request->validate([
            'block' => ['required', 'string', Rule::exists('block_definitions', 'slug')->where('is_official', true)],
        ]);

        $definition = BlockDefinition::query()->where('slug', $validated['block'])->sole();
        $version = $definition->versions()->latest('id')->firstOrFail();

        $block = DB::transaction(function () use ($page, $version, $defaults): BlockInstance {
            $block = new BlockInstance([
                'sort_order' => $page->blocks()->count(),
                'state_json' => $defaults->fromSchema($version->schema_json),
            ]);
            $block->page()->associate($page);
            $block->version()->associate($version);
            $block->save();

            return $block;
        });

        return $this->backTo($site, $page, $block);
    }

    public function state(Request $request, Site $site, BlockInstance $block): RedirectResponse
    {
        $this->scope->block($site, $block);
        Gate::authorize('editContent', $site);
        $validated = $request->validate(['state' => ['present', 'array']]);

        try {
            $block->update(['state_json' => $validated['state']]);
        } catch (InvalidBlockStateException $exception) {
            throw ValidationException::withMessages($exception->errors);
        }

        return $this->backTo($site, $block->page, $block);
    }

    public function move(Request $request, Site $site, BlockInstance $block): RedirectResponse
    {
        $this->authorizeStructure($site, $block);
        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $this->arrange->move($block, $validated['direction'] === 'up' ? -1 : 1);

        return $this->backTo($site, $block->page, $block);
    }

    public function duplicate(Site $site, BlockInstance $block): RedirectResponse
    {
        $this->authorizeStructure($site, $block);

        $copy = DB::transaction(function () use ($block): BlockInstance {
            $copy = new BlockInstance(['state_json' => $block->state_json, 'sort_order' => $block->sort_order]);
            $copy->is_hidden = $block->is_hidden;
            $copy->page()->associate($block->page);
            $copy->version()->associate($block->version);
            $copy->save();
            $this->arrange->insertAfter($copy, $block);

            return $copy;
        });

        return $this->backTo($site, $block->page, $copy);
    }

    public function visibility(Request $request, Site $site, BlockInstance $block): RedirectResponse
    {
        $this->authorizeStructure($site, $block);
        $validated = $request->validate(['hidden' => ['required', 'boolean']]);

        BlockInstance::query()->whereKey($block->id)->update(['is_hidden' => (bool) $validated['hidden']]);

        return $this->backTo($site, $block->page, $block);
    }

    public function destroy(Site $site, BlockInstance $block): RedirectResponse
    {
        $this->authorizeStructure($site, $block);
        $page = $block->page;
        $block->delete();

        return $this->backTo($site, $page);
    }

    private function authorizeStructure(Site $site, BlockInstance $block): void
    {
        $this->scope->block($site, $block);
        Gate::authorize('editDesign', $site);
    }

    private function backTo(Site $site, Page $page, ?BlockInstance $block = null): RedirectResponse
    {
        return to_route('sites.designer', array_filter([
            'site' => $site->public_id,
            'page' => $page->public_id,
            'block' => $block?->public_id,
        ]));
    }
}
