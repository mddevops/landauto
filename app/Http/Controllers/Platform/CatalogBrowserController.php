<?php

namespace App\Http\Controllers\Platform;

use App\Catalog\CatalogLevel;
use App\Enums\Catalog\DriveType;
use App\Enums\Catalog\EngineType;
use App\Enums\Catalog\TransmissionType;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Catalog\CatalogModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cascading platform catalog browser. A lower selection is honoured only when it belongs to
 * the selected parent, so changing an upper level clears everything below it.
 */
class CatalogBrowserController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $levels = [];
        $parent = null;

        foreach (CatalogLevel::cases() as $level) {
            if ($level->parent() !== null && $parent === null) {
                break;
            }

            $query = $level->modelClass()::query()->ordered();

            if ($parent !== null) {
                $query->where((string) $level->parentKey(), $parent->getKey());
            }

            if ($level === CatalogLevel::Models) {
                $query->with('parent');
            }

            $items = $query->get();
            $requested = $request->query($level->selectionKey());
            $selected = is_string($requested) && $level !== CatalogLevel::Equipments
                ? $items->first(fn (CatalogModel $item): bool => $item->public_id === $requested)
                : null;

            $levels[] = [
                'key' => $level->value,
                'label' => $level->label(),
                'selectionKey' => $level->selectionKey(),
                'parent' => $parent?->public_id,
                'selected' => $selected?->public_id,
                'items' => $items->map(fn (CatalogModel $item): array => $level->present($item))->values()->all(),
            ];

            $parent = $selected;
        }

        return Inertia::render('platform/catalog/index', [
            'levels' => $levels,
            'choices' => [
                'engine' => self::choices(EngineType::cases()),
                'transmission' => self::choices(TransmissionType::cases()),
                'drive' => self::choices(DriveType::cases()),
            ],
            'can' => [
                'edit' => Gate::allows(PlatformPermission::EditCatalog->value),
                'manageMedia' => Gate::allows(PlatformPermission::ManageCatalogMedia->value),
            ],
        ]);
    }

    /**
     * @param  list<EngineType|TransmissionType|DriveType>  $cases
     * @return list<array{value: string, label: string}>
     */
    public static function choices(array $cases): array
    {
        return array_map(fn (EngineType|TransmissionType|DriveType $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
        ], $cases);
    }
}
