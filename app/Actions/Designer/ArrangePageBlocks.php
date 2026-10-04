<?php

namespace App\Actions\Designer;

use App\Models\BlockInstance;
use App\Models\Page;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the Block order of a Page dense (0..n-1) whenever a Block is moved or inserted.
 */
final class ArrangePageBlocks
{
    public function move(BlockInstance $block, int $offset): void
    {
        DB::transaction(function () use ($block, $offset): void {
            $ids = $this->orderedIds($block->page);
            $from = array_search($block->id, $ids, true);
            $to = max(0, min(count($ids) - 1, (int) $from + $offset));

            array_splice($ids, (int) $from, 1);
            array_splice($ids, $to, 0, [$block->id]);
            $this->persist($ids);
        });
    }

    public function insertAfter(BlockInstance $block, ?BlockInstance $anchor): void
    {
        DB::transaction(function () use ($block, $anchor): void {
            $ids = array_values(array_diff($this->orderedIds($block->page), [$block->id]));
            $position = $anchor === null ? count($ids) : (int) array_search($anchor->id, $ids, true) + 1;

            array_splice($ids, $position, 0, [$block->id]);
            $this->persist($ids);
        });
    }

    /**
     * @return list<int>
     */
    private function orderedIds(Page $page): array
    {
        /** @var list<int> $ids */
        $ids = $page->blocks()->orderBy('id')->lockForUpdate()->pluck('id')->all();

        return $ids;
    }

    /**
     * @param  list<int>  $ids
     */
    private function persist(array $ids): void
    {
        foreach ($ids as $position => $id) {
            BlockInstance::query()->whereKey($id)->update(['sort_order' => $position]);
        }
    }
}
