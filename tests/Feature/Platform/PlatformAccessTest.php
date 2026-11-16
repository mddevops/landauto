<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultWorkspacePlan;
use App\Support\FullWorkspacePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $target;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['email' => 'admin@landflow.test']);
        PlatformRoleAssignment::query()->create([
            'user_id' => $this->superAdmin->id,
            'role' => PlatformRole::SuperAdmin->value,
        ]);

        $this->target = User::factory()->create(['email' => 'dealer@example.ru']);
        $this->workspace = Workspace::factory()->create(['name' => 'Салон']);
        $this->workspace->addMember($this->target, WorkspaceRole::Owner);
        $this->workspace->plan()->associate(app(DefaultWorkspacePlan::class)->resolve())->save();
    }

    public function test_super_admin_finds_user_and_assigns_plan_and_roles(): void
    {
        $this->asAdmin()->get(route('platform.access.index', ['email' => 'dealer@example.ru']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/access/index')
                ->where('user.email', 'dealer@example.ru')
                ->where('user.workspaces.0.public_id', $this->workspace->public_id)
                ->where('user.workspaces.0.plan_key', Plan::FREE_KEY)
                ->where('user.roles', []));

        $this->asAdmin()->put(route('platform.access.plan.update'), [
            'email' => 'dealer@example.ru',
            'workspace' => $this->workspace->public_id,
            'plan' => Plan::FULL_KEY,
        ])->assertRedirect(route('platform.access.index', ['email' => 'dealer@example.ru']));

        $this->assertSame(Plan::FULL_KEY, $this->workspace->fresh()?->plan?->key);

        $this->asAdmin()->put(route('platform.access.roles.update'), [
            'email' => 'dealer@example.ru',
            'roles' => [PlatformRole::CatalogManager->value],
        ])->assertRedirect();

        $this->assertTrue(
            PlatformRoleAssignment::query()
                ->where('user_id', $this->target->id)
                ->where('role', PlatformRole::CatalogManager->value)
                ->exists(),
        );
    }

    public function test_cannot_revoke_own_or_last_super_admin_role(): void
    {
        $this->asAdmin()->put(route('platform.access.roles.update'), [
            'email' => 'admin@landflow.test',
            'roles' => [],
        ])->assertSessionHasErrors('roles');

        $this->assertTrue(
            PlatformRoleAssignment::query()
                ->where('user_id', $this->superAdmin->id)
                ->where('role', PlatformRole::SuperAdmin->value)
                ->exists(),
        );
    }

    public function test_catalog_manager_and_guests_are_forbidden(): void
    {
        $manager = User::factory()->create();
        PlatformRoleAssignment::query()->create([
            'user_id' => $manager->id,
            'role' => PlatformRole::CatalogManager->value,
        ]);

        $this->actingAs($manager)->get(route('platform.access.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('platform.access.index'))->assertRedirect(route('login'));
    }

    public function test_plan_requires_owner_workspace(): void
    {
        $foreign = Workspace::factory()->create();
        $foreign->plan()->associate(app(FullWorkspacePlan::class)->resolve())->save();

        $this->asAdmin()->put(route('platform.access.plan.update'), [
            'email' => 'dealer@example.ru',
            'workspace' => $foreign->public_id,
            'plan' => Plan::FULL_KEY,
        ])->assertSessionHasErrors('workspace');
    }

    private function asAdmin(): self
    {
        return $this->actingAs($this->superAdmin);
    }
}
