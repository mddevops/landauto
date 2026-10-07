<?php

namespace App\Blocks;

use App\Models\BlockDefinition;

/**
 * Safe authoring props: public ID and readable ownership only, never internal or audit keys.
 */
final class BlockAuthoringPresenter
{
    /**
     * @return array{public_id: string, name: string, slug: string, versions_count: int, updated_at: string|null}
     */
    public function listItem(BlockDefinition $block): array
    {
        return [
            'public_id' => $block->public_id,
            'name' => $block->name,
            'slug' => $block->slug,
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
}
