<?php

namespace App\Support;

use App\Enums\Entitlement;
use App\Models\Plan;

/**
 * Resolves the «Полный» plan that operators assign by hand (`plan:assign`). Created on first use;
 * existing entitlement values are never overwritten, so a Super Admin may tune them later.
 */
final class FullWorkspacePlan
{
    /**
     * @var array<string, bool|int>
     */
    public const ENTITLEMENTS = [
        'max_sites' => 100,
        'max_members' => 50,
        'max_storage_mb' => 10_240,
        'custom_domain' => true,
        'remove_branding' => true,
        'multi_page_sites' => true,
    ];

    public function resolve(): Plan
    {
        $plan = Plan::query()->createOrFirst(
            ['key' => Plan::FULL_KEY],
            ['name' => 'Полный', 'is_active' => true],
        );

        if ($plan->wasRecentlyCreated) {
            foreach (self::ENTITLEMENTS as $key => $value) {
                $plan->setEntitlement(Entitlement::from($key), $value);
            }
        }

        return $plan;
    }
}
