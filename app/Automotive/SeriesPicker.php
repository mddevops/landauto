<?php

namespace App\Automotive;

use App\Catalog\CatalogLevel;
use App\Models\Catalog\CatalogModel;
use Illuminate\Http\Request;

/**
 * Cascading read-only catalog picker (mark → model → generation → series) for customer screens.
 * Only available catalog rows are listed and only public IDs leave this class.
 */
final class SeriesPicker
{
    private const LEVELS = [CatalogLevel::Marks, CatalogLevel::Models, CatalogLevel::Generations, CatalogLevel::Series];

    /**
     * @param  array<string, mixed>  $added  Series public IDs already added, as keys
     * @return list<array{key: string, label: string, selectionKey: string, selected: string|null, items: list<array{public_id: string, name: string, added: bool}>}>
     */
    public function levels(Request $request, array $added): array
    {
        $levels = [];
        $parent = null;

        foreach (self::LEVELS as $level) {
            if ($level->parent() !== null && $parent === null) {
                break;
            }

            $query = $level->modelClass()::query()->scopes(['available'])->ordered();

            if ($parent !== null) {
                $query->where((string) $level->parentKey(), $parent->getKey());
            }

            $items = $query->get();
            $requested = $request->query($level->selectionKey());
            $selected = is_string($requested)
                ? $items->first(fn (CatalogModel $item): bool => $item->public_id === $requested)
                : null;

            $levels[] = [
                'key' => $level->value,
                'label' => $level->label(),
                'selectionKey' => $level->selectionKey(),
                'selected' => $selected?->public_id,
                'items' => array_values($items->map(fn (CatalogModel $item): array => [
                    'public_id' => $item->public_id,
                    'name' => (string) $item->getAttribute('name'),
                    'added' => $level === CatalogLevel::Series && isset($added[$item->public_id]),
                ])->all()),
            ];

            $parent = $level === CatalogLevel::Series ? null : $selected;
        }

        return $levels;
    }
}
