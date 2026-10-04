<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Http\Middleware\EnsurePlatformPermission;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_owner_and_admin_get_no_platform_permissions(): void
    {
        $first = User::factory()->create();
        $this->assertSame(1, $first->id);

        foreach ([WorkspaceRole::Owner, WorkspaceRole::Admin] as $role) {
            $user = User::factory()->create();
            $workspace = Workspace::factory()->create();
            $workspace->addMember($user, $role);
            $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])->get(route('dashboard'));

            foreach (PlatformPermission::cases() as $permission) {
                $this->assertFalse(Gate::forUser($user)->allows($permission->value), "{$role->value} {$permission->value}");
            }
        }

        foreach (PlatformPermission::cases() as $permission) {
            $this->assertFalse(Gate::forUser($first)->allows($permission->value), 'user #1 is not special');
        }
    }

    public function test_platform_roles_grant_catalog_permissions(): void
    {
        $manager = $this->userWithRole(PlatformRole::CatalogManager);
        $superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);

        foreach ([$manager, $superAdmin] as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('view_catalog'));
            $this->assertTrue(Gate::forUser($user)->allows('edit_catalog'));
            $this->assertTrue(Gate::forUser($user)->allows('manage_catalog_media'));
            $this->assertFalse(Gate::forUser($user)->allows('edit_design'), 'platform roles grant no Workspace permissions');
        }
    }

    public function test_middleware_and_shared_props_follow_platform_roles(): void
    {
        Route::middleware(['web', 'auth', EnsurePlatformPermission::class.':edit_catalog'])
            ->get('/__platform-probe', fn () => 'ok');

        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);
        $manager = $this->userWithRole(PlatformRole::CatalogManager);

        $this->get('/__platform-probe')->assertRedirect(route('login'));
        $this->actingAs($owner)->get('/__platform-probe')->assertForbidden();
        $this->actingAs($manager)->get('/__platform-probe')->assertOk();

        $this->actingAs($manager)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('platform.permissions', ['view_catalog', 'edit_catalog', 'manage_catalog_media']));
        $this->actingAs($owner)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('platform.permissions', []));
    }

    public function test_command_grants_and_revokes_roles_for_existing_users_only(): void
    {
        $user = User::factory()->create(['email' => 'catalog@example.test']);

        $this->artisan('platform:role', ['action' => 'grant', 'email' => 'Catalog@Example.test', 'role' => 'super_admin'])->assertSuccessful();
        $this->artisan('platform:role', ['action' => 'grant', 'email' => 'catalog@example.test', 'role' => 'super_admin'])->assertSuccessful();
        $this->assertSame(1, PlatformRoleAssignment::query()->where('user_id', $user->id)->count());
        $this->assertTrue(Gate::forUser($user->fresh())->allows('edit_catalog'));

        $this->artisan('platform:role', ['action' => 'grant', 'email' => 'missing@example.test', 'role' => 'super_admin'])->assertFailed();
        $this->artisan('platform:role', ['action' => 'grant', 'email' => 'catalog@example.test', 'role' => 'owner'])->assertFailed();
        $this->artisan('platform:role', ['action' => 'promote', 'email' => 'catalog@example.test', 'role' => 'super_admin'])->assertFailed();
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PlatformRoleAssignment::query()->count());

        $this->artisan('platform:role', ['action' => 'revoke', 'email' => 'catalog@example.test', 'role' => 'super_admin'])->assertSuccessful();
        $this->assertFalse(Gate::forUser($user->fresh())->allows('view_catalog'));
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }
}
