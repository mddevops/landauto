<?php

namespace App\Support;

use App\Enums\Entitlement;
use App\Models\Plan;

/**
 * Resolves the system Free plan assigned to every new personal Workspace (D-100).
 *
 * The plan is identified only by its stable key. It is created on first use with its approved
 * entitlements; an existing plan and its entitlement values are never overwritten here.
 */
final class DefaultWorkspacePlan
{
    public const FREE_MAX_SITES = 2;

    public function resolve(): Plan
    {
        $plan = Plan::query()->createOrFirst(
            ['key' => Plan::FREE_KEY],
            ['name' => 'Бесплатный', 'is_active' => true],
        );

        if ($plan->wasRecentlyCreated) {
            $plan->setEntitlement(Entitlement::MaxSites, self::FREE_MAX_SITES);
        }

        return $plan;
    }
}
