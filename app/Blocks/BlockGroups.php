<?php

namespace App\Blocks;

use App\Enums\BlockCategory;
use App\Models\BlockDefinition;
use App\Models\BlockGroup;
use Illuminate\Support\Facades\DB;

/**
 * Block Group placement (D-130): every official catalog Block starts its own group named after it;
 * any other Block without a group joins the first group of its category, created when missing.
 */
final class BlockGroups
{
    public function create(string $name, BlockCategory $kind): BlockGroup
    {
        return BlockGroup::query()->create([
            'name' => $name,
            'kind' => $kind,
            'sort_order' => ((int) BlockGroup::query()->max('sort_order')) + 1,
        ]);
    }

    /**
     * The group type is the visual category of all its Blocks.
     */
    public function update(BlockGroup $group, string $name, BlockCategory $kind): void
    {
        DB::transaction(function () use ($group, $name, $kind): void {
            $group->name = $name;
            $group->kind = $kind;
            $group->save();

            $group->blocks()->where('category', '!=', $kind->value)->update(['category' => $kind->value]);
        });
    }

    /**
     * Swaps the group with its neighbour; `$step` is -1 (up) or 1 (down).
     */
    public function move(BlockGroup $group, int $step): void
    {
        DB::transaction(function () use ($group, $step): void {
            $groups = BlockGroup::query()->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $groups->search(fn (BlockGroup $item): bool => $item->id === $group->id);

            if ($index === false || ! $groups->has($index + $step)) {
                return;
            }

            $moved = $groups->splice($index, 1)->first();
            $groups->splice($index + $step, 0, [$moved]);

            $groups->values()->each(function (BlockGroup $item, int $order): void {
                $item->sort_order = $order + 1;
                $item->save();
            });
        });
    }

    /**
     * Designer library order: by group, official Blocks before Developer Blocks inside a group.
     * Needs the `group` relation loaded.
     */
    public static function libraryOrder(BlockDefinition $block): string
    {
        $group = $block->group;

        return sprintf(
            '%010d-%010d-%d-%010d',
            $group === null ? 9_999_999_999 : $group->sort_order,
            $group === null ? 0 : $group->id,
            $block->isPlatformOwned() ? 0 : 1,
            $block->id,
        );
    }

    public function backfill(): void
    {
        $official = array_column(OfficialBlockCatalog::blocks(), 'slug');

        BlockDefinition::query()->whereNull('block_group_id')->orderBy('id')->get()
            ->sortBy(fn (BlockDefinition $block): int => in_array($block->slug, $official, true)
                ? (int) array_search($block->slug, $official, true)
                : PHP_INT_MAX)
            ->each(function (BlockDefinition $block) use ($official): void {
                $group = $block->isPlatformOwned() && in_array($block->slug, $official, true)
                    ? $this->named($block->name, $block->category)
                    : $this->forKind($block->category);

                $block->group()->associate($group);
                $block->saveQuietly();
            });
    }

    private function named(string $name, BlockCategory $kind): BlockGroup
    {
        return BlockGroup::query()->where('name', $name)->first() ?? $this->create($name, $kind);
    }

    private function forKind(BlockCategory $kind): BlockGroup
    {
        return BlockGroup::query()->where('kind', $kind->value)->orderBy('sort_order')->orderBy('id')->first()
            ?? $this->named($kind->label(), $kind);
    }
}
