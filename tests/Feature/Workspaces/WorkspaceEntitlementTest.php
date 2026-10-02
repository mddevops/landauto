<?php

namespace Tests\Feature\Workspaces;

use App\Enums\Entitlement;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Workspace;
use App\Support\WorkspaceEntitlements;
use App\Support\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class WorkspaceEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_boolean_entitlement_can_be_enabled(): void
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::CustomDomain, true);
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($this->entitlements()->allows($workspace, Entitlement::CustomDomain));
    }

    public function test_boolean_entitlement_can_be_disabled(): void
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::RemoveBranding, false);
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);

        $this->assertFalse($this->entitlements()->allows($workspace, Entitlement::RemoveBranding));
    }

    public function test_numeric_limit_is_resolved_as_an_integer(): void
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, 7);
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);

        $this->assertSame(7, $this->entitlements()->limit($workspace, Entitlement::MaxSites));
    }

    public function test_different_workspaces_resolve_only_their_own_plan_entitlements(): void
    {
        $limitedPlan = Plan::factory()->create();
        $limitedPlan->setEntitlement(Entitlement::MaxMembers, 1);
        $limitedPlan->setEntitlement(Entitlement::CustomDomain, false);

        $expandedPlan = Plan::factory()->create();
        $expandedPlan->setEntitlement(Entitlement::MaxMembers, 12);
        $expandedPlan->setEntitlement(Entitlement::CustomDomain, true);

        $limitedWorkspace = Workspace::factory()->create(['plan_id' => $limitedPlan->id]);
        $expandedWorkspace = Workspace::factory()->create(['plan_id' => $expandedPlan->id]);

        $this->assertSame(1, $this->entitlements()->limit($limitedWorkspace, Entitlement::MaxMembers));
        $this->assertFalse($this->entitlements()->allows($limitedWorkspace, Entitlement::CustomDomain));
        $this->assertSame(12, $this->entitlements()->limit($expandedWorkspace, Entitlement::MaxMembers));
        $this->assertTrue($this->entitlements()->allows($expandedWorkspace, Entitlement::CustomDomain));
    }

    public function test_missing_or_inactive_plan_values_use_safe_defaults(): void
    {
        $workspaceWithoutPlan = Workspace::factory()->create();
        $inactivePlan = Plan::factory()->inactive()->create();
        $inactivePlan->setEntitlement(Entitlement::CustomDomain, true);
        $inactivePlan->setEntitlement(Entitlement::MaxSites, 50);
        $workspaceWithInactivePlan = Workspace::factory()->create(['plan_id' => $inactivePlan->id]);

        $this->assertFalse($this->entitlements()->allows($workspaceWithoutPlan, Entitlement::CustomDomain));
        $this->assertSame(0, $this->entitlements()->limit($workspaceWithoutPlan, Entitlement::MaxSites));
        $this->assertFalse($this->entitlements()->allows($workspaceWithInactivePlan, Entitlement::CustomDomain));
        $this->assertSame(0, $this->entitlements()->limit($workspaceWithInactivePlan, Entitlement::MaxSites));
    }

    public function test_workspace_permission_does_not_enable_an_entitlement(): void
    {
        $workspace = Workspace::factory()->create();
        $ownerPermissions = app(WorkspacePermissionResolver::class)->forRole(WorkspaceRole::Owner);

        $this->assertContains(WorkspacePermission::ManageDomains, $ownerPermissions);
        $this->assertFalse($this->entitlements()->allows($workspace, Entitlement::CustomDomain));
    }

    public function test_entitlement_does_not_grant_a_workspace_permission(): void
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::CustomDomain, true);
        $workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $designerPermissions = app(WorkspacePermissionResolver::class)->forRole(WorkspaceRole::Designer);

        $this->assertTrue($this->entitlements()->allows($workspace, Entitlement::CustomDomain));
        $this->assertNotContains(WorkspacePermission::ManageDomains, $designerPermissions);
    }

    public function test_boolean_and_numeric_apis_reject_the_wrong_entitlement_type(): void
    {
        $workspace = Workspace::factory()->create();

        try {
            $this->entitlements()->allows($workspace, Entitlement::MaxSites);
            $this->fail('Numeric entitlement was accepted by the boolean API.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->entitlements()->limit($workspace, Entitlement::CustomDomain);
    }

    public function test_plan_entitlement_storage_is_internal_and_typed(): void
    {
        $plan = Plan::factory()->create();
        $entitlement = $plan->setEntitlement(Entitlement::MaxSites, 3);

        $this->assertSame(Entitlement::MaxSites, $entitlement->key);
        $this->assertSame(3, $entitlement->integer_value);
        $this->assertNull($entitlement->boolean_value);
        $this->assertArrayNotHasKey('id', $entitlement->toArray());
        $this->assertArrayNotHasKey('plan_id', $entitlement->toArray());
    }

    private function entitlements(): WorkspaceEntitlements
    {
        return app(WorkspaceEntitlements::class);
    }
}
