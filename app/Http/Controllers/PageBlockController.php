<?php

namespace App\Http\Controllers;

use App\Actions\Designer\ArrangePageBlocks;
use App\Blocks\BlockCatalogAccess;
use App\Blocks\BlockStateDefaults;
use App\Blocks\BlockVersionGrants;
use App\Blocks\LegacyBlockMigration;
use App\Blocks\SiteComponents;
use App\Blocks\SiteTypeBlockAccess;
use App\Exceptions\InvalidBlockStateException;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteComponent;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PageBlockController extends Controller
{
    public function __construct(
        private DesignerScope $scope,
        private ArrangePageBlocks $arrange,
        private BlockCatalogAccess $access,
        private BlockVersionGrants $grants,
        private SiteComponents $components,
    ) {}

    public function store(Request $request, Site $site, Page $page, BlockStateDefaults $defaults): RedirectResponse
    {
        $this->scope->page($site, $page);
        Gate::authorize('editStructure', $site);
        $validated = $request->validate(['block' => ['required', 'string', 'max:'.BlockDefinition::SLUG_MAX]]);

        // Same set as the Designer library: catalog Blocks that have a published version.
        $definition = BlockDefinition::query()
            ->inCatalog()
            ->whereHas('versions')
            ->where('slug', $validated['block'])
            ->first() ?? throw ValidationException::withMessages(['block' => 'Этот блок недоступен.']);
        $version = $definition->versions()->latest('id')->firstOrFail();
        $this->authorizeCurrentAccess($site, $definition);

        if ($definition->is_component) {
            $component = $this->components->place($site, $version);

            return $this->backTo($site, $page, $component->public_id);
        }

        $block = DB::transaction(function () use ($page, $version, $defaults, $site): BlockInstance {
            $block = new BlockInstance([
                'sort_order' => $page->blocks()->count(),
                'state_json' => $defaults->fromSchema($version->schema_json),
            ]);
            $block->page()->associate($page);
            $block->version()->associate($version);
            $block->save();
            $this->grants->grant($site, [$version->id]);

            return $block;
        });

        return $this->backTo($site, $page, $block->public_id);
    }

    public function state(Request $request, Site $site, string $block, SiteTypeBlockAccess $siteTypeAccess): RedirectResponse
    {
        $validated = $request->validate(['state' => ['present', 'array']]);
        $page = $this->resolvePageForRedirect($site, $request);

        if (($component = $this->components->findOnSite($site, $block)) !== null) {
            $this->scope->site($site);
            Gate::authorize('editContent', $site);

            try {
                $component->update(['state_json' => $siteTypeAccess->merge($site, $component->version->schema_json, $component->state_json, $validated['state'])]);
            } catch (InvalidBlockStateException $exception) {
                throw ValidationException::withMessages($exception->errors);
            }

            return $this->backTo($site, $page ?? $this->homePage($site), $component->public_id);
        }

        $instance = $this->pageBlock($site, $block);
        Gate::authorize('editContent', $site);

        try {
            $instance->update(['state_json' => $siteTypeAccess->merge($site, $instance->version->schema_json, $instance->state_json, $validated['state'])]);
        } catch (InvalidBlockStateException $exception) {
            throw ValidationException::withMessages($exception->errors);
        }

        return $this->backTo($site, $instance->page, $instance->public_id);
    }

    public function move(Request $request, Site $site, string $block): RedirectResponse
    {
        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $page = $this->resolvePageForRedirect($site, $request);

        if (($component = $this->components->findOnSite($site, $block)) !== null) {
            $this->scope->site($site);
            Gate::authorize('editStructure', $site);
            $this->components->moveFree($component, $validated['direction'] === 'up' ? -1 : 1);

            return $this->backTo($site, $page ?? $this->homePage($site), $component->public_id);
        }

        $instance = $this->pageBlock($site, $block);
        Gate::authorize('editStructure', $site);
        $this->arrange->move($instance, $validated['direction'] === 'up' ? -1 : 1);

        return $this->backTo($site, $instance->page, $instance->public_id);
    }

    public function duplicate(Site $site, string $block, Request $request): RedirectResponse
    {
        if ($this->components->findOnSite($site, $block) !== null) {
            throw ValidationException::withMessages(['block' => 'Компонент нельзя дублировать.']);
        }

        $instance = $this->pageBlock($site, $block);
        Gate::authorize('editStructure', $site);

        // Reusing the exact version this Site already installed lawfully needs no new acquisition (D-122).
        if (! $this->grants->has($site, $instance->version)) {
            $this->authorizeCurrentAccess($site, $instance->version->definition);
        }

        $copy = DB::transaction(function () use ($site, $instance): BlockInstance {
            $copy = new BlockInstance(['state_json' => $instance->state_json, 'sort_order' => $instance->sort_order]);
            $copy->is_hidden = $instance->is_hidden;
            $copy->page()->associate($instance->page);
            $copy->version()->associate($instance->version);
            $copy->save();
            $this->grants->grant($site, [$instance->block_version_id]);
            $this->arrange->insertAfter($copy, $instance);

            return $copy;
        });

        return $this->backTo($site, $instance->page, $copy->public_id);
    }

    /**
     * Moves a legacy sandboxed Block to its approved Native version (ADR-009 §10); Quiz / Chat Sites
     * only get upgrades through a new Template version.
     */
    public function upgrade(Site $site, string $block, LegacyBlockMigration $migration, Request $request): RedirectResponse
    {
        if (($component = $this->components->findOnSite($site, $block)) !== null) {
            $this->scope->site($site);
            Gate::authorize('editStructure', $site);
            $version = $migration->upgradeSiteComponent($site, $component);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Блок обновлён до нативной версии {$version->version}."]);

            return $this->backTo($site, $this->resolvePageForRedirect($site, $request) ?? $this->homePage($site), $component->public_id);
        }

        $instance = $this->pageBlock($site, $block);
        Gate::authorize('editStructure', $site);
        $version = $migration->upgradeSiteBlock($site, $instance);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Блок обновлён до нативной версии {$version->version}."]);

        return $this->backTo($site, $instance->page, $instance->public_id);
    }

    public function visibility(Request $request, Site $site, string $block): RedirectResponse
    {
        $validated = $request->validate(['hidden' => ['required', 'boolean']]);
        $page = $this->resolvePageForRedirect($site, $request);

        if (($component = $this->components->findOnSite($site, $block)) !== null) {
            $this->scope->site($site);
            Gate::authorize('editStructure', $site);
            SiteComponent::query()->whereKey($component->id)->update(['is_hidden' => (bool) $validated['hidden']]);

            return $this->backTo($site, $page ?? $this->homePage($site), $component->public_id);
        }

        $instance = $this->pageBlock($site, $block);
        Gate::authorize('editStructure', $site);
        BlockInstance::query()->whereKey($instance->id)->update(['is_hidden' => (bool) $validated['hidden']]);

        return $this->backTo($site, $instance->page, $instance->public_id);
    }

    public function destroy(Site $site, string $block, Request $request): RedirectResponse
    {
        $page = $this->resolvePageForRedirect($site, $request);

        if (($component = $this->components->findOnSite($site, $block)) !== null) {
            $this->scope->site($site);
            Gate::authorize('editStructure', $site);
            $component->delete();

            return $this->backTo($site, $page ?? $this->homePage($site));
        }

        $instance = $this->pageBlock($site, $block);
        Gate::authorize('editStructure', $site);
        $page = $instance->page;
        $instance->delete();

        return $this->backTo($site, $page);
    }

    private function authorizeCurrentAccess(Site $site, BlockDefinition $definition): void
    {
        $denial = $this->access->denial($site, $definition);

        if ($denial !== null) {
            throw ValidationException::withMessages(['block' => $denial]);
        }
    }

    private function pageBlock(Site $site, string $publicId): BlockInstance
    {
        $block = BlockInstance::query()->where('public_id', $publicId)->firstOrFail();
        $this->scope->block($site, $block);

        return $block;
    }

    private function homePage(Site $site): Page
    {
        return $site->pages()->where('is_home', true)->firstOrFail();
    }

    private function resolvePageForRedirect(Site $site, Request $request): ?Page
    {
        $pageId = $request->query('page') ?? $request->input('page');

        if (! is_string($pageId) || $pageId === '') {
            return null;
        }

        $page = $site->pages()->where('public_id', $pageId)->first();

        return $page !== null ? $this->scope->page($site, $page) : null;
    }

    private function backTo(Site $site, Page $page, ?string $blockPublicId = null): RedirectResponse
    {
        return to_route('sites.designer', array_filter([
            'site' => $site->public_id,
            'page' => $page->public_id,
            'block' => $blockPublicId,
        ]));
    }
}
