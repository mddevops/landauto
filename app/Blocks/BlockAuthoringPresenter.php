<?php

namespace App\Blocks;

use App\Enums\BlockCategory;
use App\Enums\BlockRuntime;
use App\Models\BlockDefinition;
use App\Models\BlockDraft;
use App\Models\BlockVersion;
use stdClass;

/**
 * Safe authoring props: public ID and readable ownership only, never internal or audit keys.
 */
final class BlockAuthoringPresenter
{
    public function __construct(
        private BlockStudio $studio,
        private BlockSourceChecker $checker,
    ) {}

    /**
     * @return array{public_id: string, name: string, slug: string, category: string, category_label: string, versions_count: int, updated_at: string|null}
     */
    public function listItem(BlockDefinition $block): array
    {
        return [
            'public_id' => $block->public_id,
            'name' => $block->name,
            'slug' => $block->slug,
            'category' => $block->category->value,
            'category_label' => $block->category->label(),
            'versions_count' => (int) $block->getAttribute('versions_count'),
            'updated_at' => $block->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function detail(BlockDefinition $block): array
    {
        return [
            ...$this->listItem($block),
            'owner_scope' => $block->owner_scope->value,
            'owner_scope_label' => $block->owner_scope->label(),
            'owner_name' => $block->isPlatformOwned() ? 'Landflow' : (string) $block->developerProfile?->display_name,
            'created_at' => $block->created_at?->toIso8601String(),
        ];
    }

    /**
     * Block Studio page props: metadata, the Draft sources and the current schema errors.
     *
     * @return array<string, mixed>
     */
    public function studio(BlockDefinition $block): array
    {
        $versions = $block->versions()->latest('id')->get();
        $official = $versions->contains(fn (BlockVersion $version): bool => $version->runtime === BlockRuntime::Official);

        return [
            'block' => $this->detail($block),
            'draft' => $this->draft($this->studio->draftFor($block)),
            'versions' => $versions->map(fn (BlockVersion $version): array => [
                'version' => $version->version,
                'runtime_label' => $version->runtime->label(),
                'published_at' => $version->created_at?->toIso8601String(),
            ])->values()->all(),
            'publishBlockedReason' => $official ? BlockPublisher::OFFICIAL_RUNTIME : null,
            'categories' => BlockCategory::options(),
            'sourceMaxBytes' => BlockStudio::SOURCE_MAX_BYTES,
        ];
    }

    /**
     * @return array{revision: int, sources: array{html: string, css: string, js: string, schema: string}, preview: array<string, mixed>|stdClass, checks: list<array{source: string, line: int|null, path: string|null, message: string}>, saved_at: string|null}
     */
    public function draft(BlockDraft $draft): array
    {
        $sources = BlockStudio::sources($draft);

        return [
            'revision' => $draft->revision,
            'sources' => $sources,
            // An empty preview must reach the browser as an object, not a list.
            'preview' => $draft->preview_data ?: new stdClass,
            'checks' => $this->checker->check($sources),
            'saved_at' => $draft->exists ? $draft->updated_at?->toIso8601String() : null,
        ];
    }
}
