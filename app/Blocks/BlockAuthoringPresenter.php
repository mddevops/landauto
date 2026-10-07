<?php

namespace App\Blocks;

use App\Enums\BlockCategory;
use App\Models\BlockDefinition;
use App\Models\BlockDraft;

/**
 * Safe authoring props: public ID and readable ownership only, never internal or audit keys.
 */
final class BlockAuthoringPresenter
{
    public function __construct(private BlockStudio $studio) {}

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
        return [
            'block' => $this->detail($block),
            'draft' => $this->draft($this->studio->draftFor($block)),
            'categories' => BlockCategory::options(),
            'sourceMaxBytes' => BlockStudio::SOURCE_MAX_BYTES,
        ];
    }

    /**
     * @return array{revision: int, sources: array<string, string>, schema_errors: list<array{path: string, message: string}>, saved_at: string|null}
     */
    public function draft(BlockDraft $draft): array
    {
        $sources = [];

        foreach (BlockStudio::SOURCES as $key => $column) {
            $sources[$key] = (string) $draft->getAttribute($column);
        }

        $errors = [];

        foreach ($this->studio->schemaErrors($sources['schema']) as $path => $message) {
            $errors[] = ['path' => $path, 'message' => $message];
        }

        return [
            'revision' => $draft->revision,
            'sources' => $sources,
            'schema_errors' => $errors,
            'saved_at' => $draft->exists ? $draft->updated_at?->toIso8601String() : null,
        ];
    }
}
