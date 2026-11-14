<?php

namespace Tests\Feature\Workspaces;

use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAssignCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_plan_is_created_and_assigned_to_the_owned_workspace(): void
    {
        $owner = User::factory()->create(['email' => 'dealer@example.test']);
        $workspace = Workspace::factory()->create();
        $workspace->addMember($owner, WorkspaceRole::Owner);

        $this->artisan('plan:assign', ['email' => 'Dealer@Example.test'])->assertSuccessful();

        $plan = $workspace->refresh()->plan;
        $this->assertNotNull($plan);
        $this->assertSame(Plan::FULL_KEY, $plan->key);
        $this->assertSame('Полный', $plan->name);
        $entitlements = app(WorkspaceEntitlements::class);
        $this->assertTrue($entitlements->allows($workspace, Entitlement::MultiPageSites));
        $this->assertTrue($entitlements->allows($workspace, Entitlement::CustomDomain));
        $this->assertSame(100, $entitlements->limit($workspace, Entitlement::MaxSites));

        $this->artisan('plan:assign', ['email' => 'dealer@example.test', 'plan' => 'free'])->assertSuccessful();
        $this->assertSame(Plan::FREE_KEY, $workspace->refresh()->plan?->key);
        $this->assertSame(1, Plan::query()->where('key', Plan::FULL_KEY)->count());
    }

    public function test_only_owned_workspaces_are_changed(): void
    {
        $user = User::factory()->create(['email' => 'member@example.test']);
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Admin);

        $this->artisan('plan:assign', ['email' => 'member@example.test'])->assertFailed();
        $this->artisan('plan:assign', ['email' => 'nobody@example.test'])->assertFailed();
        $this->artisan('plan:assign', ['email' => 'member@example.test', 'plan' => 'gold'])->assertFailed();
        $this->assertNull($workspace->refresh()->plan_id);
    }

    public function test_several_owned_workspaces_require_an_explicit_choice(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $first = Workspace::factory()->create();
        $second = Workspace::factory()->create();
        $first->addMember($owner, WorkspaceRole::Owner);
        $second->addMember($owner, WorkspaceRole::Owner);

        $this->artisan('plan:assign', ['email' => 'owner@example.test'])->assertFailed();

        $this->artisan('plan:assign', ['email' => 'owner@example.test', '--workspace' => $second->public_id])->assertSuccessful();
        $this->assertSame(Plan::FULL_KEY, $second->refresh()->plan?->key);
        $this->assertNull($first->refresh()->plan_id);
    }
}
