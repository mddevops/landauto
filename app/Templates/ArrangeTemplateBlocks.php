<?php

namespace App\Templates;

use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the Block order of a Template Page dense (0..n-1) whenever a Block is moved or inserted.
 */
final class ArrangeTemplateBlocks
{
    public function move(TemplateBlock $block, int $offset): void
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

    public function insertAfter(TemplateBlock $block, TemplateBlock $anchor): void
    {
        DB::transaction(function () use ($block, $anchor): void {
            $ids = array_values(array_diff($this->orderedIds($block->page), [$block->id]));
            array_splice($ids, (int) array_search($anchor->id, $ids, true) + 1, 0, [$block->id]);
            $this->persist($ids);
        });
    }

    /**
     * @return list<int>
     */
    private function orderedIds(TemplatePage $page): array
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
            TemplateBlock::query()->whereKey($id)->update(['sort_order' => $position]);
        }
    }
}
