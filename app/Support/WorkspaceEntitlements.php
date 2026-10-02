<?php

namespace App\Support;

use App\Enums\Entitlement;
use App\Enums\EntitlementValueType;
use App\Models\PlanEntitlement;
use App\Models\Workspace;
use InvalidArgumentException;

final class WorkspaceEntitlements
{
    public function allows(Workspace $workspace, Entitlement $entitlement): bool
    {
        $this->ensureType($entitlement, EntitlementValueType::Boolean);
        $value = $this->find($workspace, $entitlement);

        return $value?->value_type === EntitlementValueType::Boolean
            ? (bool) $value->boolean_value
            : false;
    }

    public function limit(Workspace $workspace, Entitlement $entitlement): int
    {
        $this->ensureType($entitlement, EntitlementValueType::Integer);
        $value = $this->find($workspace, $entitlement);

        return $value?->value_type === EntitlementValueType::Integer
            ? max(0, (int) $value->integer_value)
            : 0;
    }

    private function find(Workspace $workspace, Entitlement $entitlement): ?PlanEntitlement
    {
        if ($workspace->plan_id === null) {
            return null;
        }

        return PlanEntitlement::query()
            ->where('plan_id', $workspace->plan_id)
            ->where('key', $entitlement->value)
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->first();
    }

    private function ensureType(Entitlement $entitlement, EntitlementValueType $expected): void
    {
        if ($entitlement->valueType() !== $expected) {
            throw new InvalidArgumentException("Entitlement {$entitlement->value} is not {$expected->value}.");
        }
    }
}
