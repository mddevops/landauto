<?php

namespace App\Blocks;

use App\Enums\ComponentPosition;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Site-level Components (ADR-013): place once, compose onto every Page for Designer / Preview / Publish.
 */
final class SiteComponents
{
    public function __construct(
        private BlockStateDefaults $defaults,
        private BlockVersionGrants $grants,
    ) {}

    /**
     * Composed list for a Page: first Components, page Blocks, free Components, last Components.
     *
     * @return Collection<int, BlockInstance|SiteComponent>
     */
    public function forPage(Page $page, bool $includeHidden = true): Collection
    {
        $components = SiteComponent::query()
            ->where('site_id', $page->site_id)
            ->with('version.definition')
            ->orderBy('id')
            ->get();

        $first = $components
            ->filter(fn (SiteComponent $component): bool => $component->version->definition->component_position === ComponentPosition::First)
            ->values();
        $free = $components
            ->filter(fn (SiteComponent $component): bool => $component->version->definition->component_position === ComponentPosition::Free)
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
        $last = $components
            ->filter(fn (SiteComponent $component): bool => $component->version->definition->component_position === ComponentPosition::Last)
            ->values();

        $pageBlocks = $page->blocks()
            ->with('version.definition')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn (BlockInstance $block): bool => $block->version->definition->is_component)
            ->values();

        $merged = $first->concat($pageBlocks)->concat($free)->concat($last);

        if (! $includeHidden) {
            $merged = $merged->reject(fn (BlockInstance|SiteComponent $block): bool => $block->is_hidden)->values();
        }

        return $merged;
    }

    /**
     * Places a Component Definition on the Site (at most once). Caller must authorize structure and access.
     *
     * @param  array<string, mixed>|null  $state
     *
     * @throws ValidationException
     */
    public function place(Site $site, BlockVersion $version, ?array $state = null): SiteComponent
    {
        $definition = $version->definition;

        if (! $definition->is_component || $definition->component_position === null) {
            throw ValidationException::withMessages(['block' => 'Этот блок не является компонентом.']);
        }

        if ($this->hasDefinition($site, $definition)) {
            throw ValidationException::withMessages(['block' => 'Этот компонент уже добавлен на сайт.']);
        }

        return DB::transaction(function () use ($site, $version, $definition, $state): SiteComponent {
            $sortOrder = $definition->component_position === ComponentPosition::Free
                ? (int) SiteComponent::query()
                    ->where('site_id', $site->id)
                    ->whereHas('version.definition', fn ($query) => $query->where('component_position', ComponentPosition::Free->value))
                    ->count()
                : 0;

            $component = new SiteComponent([
                'sort_order' => $sortOrder,
                'state_json' => $state ?? $this->defaults->fromSchema($version->schema_json),
            ]);
            $component->site()->associate($site);
            $component->version()->associate($version);
            $component->save();
            $this->grants->grant($site, [$version->id]);

            return $component;
        });
    }

    public function hasDefinition(Site $site, BlockDefinition $definition): bool
    {
        return SiteComponent::query()
            ->where('site_id', $site->id)
            ->whereHas('version', fn ($query) => $query->where('block_definition_id', $definition->id))
            ->exists();
    }

    public function findOnSite(Site $site, string $publicId): ?SiteComponent
    {
        return SiteComponent::query()
            ->where('site_id', $site->id)
            ->where('public_id', $publicId)
            ->with('version.definition')
            ->first();
    }

    /**
     * Reorders a free Component among free Components only. First / last Components are fixed.
     */
    public function moveFree(SiteComponent $component, int $offset): void
    {
        $position = $component->version->definition->component_position;

        if ($position !== ComponentPosition::Free || $offset === 0) {
            return;
        }

        DB::transaction(function () use ($component, $offset): void {
            $ids = SiteComponent::query()
                ->where('site_id', $component->site_id)
                ->whereHas('version.definition', fn ($query) => $query->where('component_position', ComponentPosition::Free->value))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $from = array_search($component->id, $ids, true);

            if ($from === false) {
                return;
            }

            $to = max(0, min(count($ids) - 1, (int) $from + $offset));
            array_splice($ids, (int) $from, 1);
            array_splice($ids, $to, 0, [$component->id]);

            foreach ($ids as $index => $id) {
                SiteComponent::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });
    }
}
