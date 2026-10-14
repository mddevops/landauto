<?php

namespace App\Catalog;

use App\Enums\CatalogAccessMode;
use App\Models\BlockDefinition;
use App\Models\Plan;
use App\Models\Template;
use App\Models\Workspace;

/** Resolves catalog inclusion for a Workspace's current active Plan (X-026). */
final class PlanCatalogAccess
{
    public function includes(Workspace $workspace, BlockDefinition|Template $item): bool
    {
        if ($workspace->plan_id === null || $item->access_mode !== CatalogAccessMode::Entitlement) {
            return false;
        }

        return $item->plans()
            ->whereKey($workspace->plan_id)
            ->where('plans.is_active', true)
            ->exists();
    }

    /** @return list<string> */
    public function includedPlanNames(BlockDefinition|Template $item): array
    {
        $names = $item->plans()
            ->where('plans.is_active', true)
            ->orderBy('plans.name')
            ->pluck('plans.name')
            ->all();

        return array_values(array_map(static fn (mixed $name): string => (string) $name, $names));
    }

    public function denial(BlockDefinition|Template $item, string $noun): string
    {
        $plans = $this->includedPlanNames($item);

        if ($plans === []) {
            return "{$noun} пока не включён ни в один тариф.";
        }

        $quoted = implode('», «', $plans);

        $target = $noun === 'Блок' ? 'блоку' : 'шаблону';

        return "Для доступа к {$target} нужен тариф «{$quoted}» или отдельная лицензия каталога.";
    }
}
