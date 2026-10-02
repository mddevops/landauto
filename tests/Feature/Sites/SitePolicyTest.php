<?php

namespace Tests\Feature\Sites;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SitePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_with_view_permission_can_view_site_in_current_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, WorkspaceRole::Designer);
        $site = Site::factory()->for($workspace)->create();
        $this->resolveContext($user, $workspace);

        $this->assertTrue(Gate::forUser($user)->allows('view', $site));
    }

    public function test_member_without_required_permission_is_denied(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, WorkspaceRole::Designer);
        $site = Site::factory()->for($workspace)->create();
        $this->resolveContext($user, $workspace);

        $this->assertFalse(Gate::forUser($user)->allows('update', $site));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $site));
        $this->assertFalse(Gate::forUser($user)->allows('create', Site::class));
    }

    public function test_owner_can_use_canonical_site_management_permissions(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, WorkspaceRole::Owner);
        $site = Site::factory()->for($workspace)->create();
        $this->resolveContext($user, $workspace);

        $this->assertTrue(Gate::forUser($user)->allows('create', Site::class));
        $this->assertTrue(Gate::forUser($user)->allows('update', $site));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $site));
    }

    public function test_suspended_membership_is_denied(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Owner, WorkspaceMemberStatus::Suspended);
        $site = Site::factory()->for($workspace)->create();
        $this->resolveContext($user);

        $this->assertFalse(Gate::forUser($user)->allows('view', $site));
    }

    public function test_user_from_another_workspace_is_denied(): void
    {
        $user = User::factory()->create();
        $currentWorkspace = $this->workspaceFor($user, WorkspaceRole::Owner);
        $foreignSite = Site::factory()->create();
        $this->resolveContext($user, $currentWorkspace);

        $this->assertFalse(Gate::forUser($user)->allows('view', $foreignSite));
    }

    public function test_current_workspace_cannot_authorize_site_from_another_membership(): void
    {
        $user = User::factory()->create();
        $currentWorkspace = $this->workspaceFor($user, WorkspaceRole::Owner);
        $otherWorkspace = $this->workspaceFor($user, WorkspaceRole::Owner);
        $otherSite = Site::factory()->for($otherWorkspace)->create();
        $this->resolveContext($user, $currentWorkspace);

        $this->assertFalse(Gate::forUser($user)->allows('view', $otherSite));
    }

    public function test_knowing_foreign_public_id_does_not_bypass_policy(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('/_authorized-sites/{site}', fn (Site $site) => response()->json([
                'public_id' => $site->public_id,
            ]))
            ->can('view', 'site');

        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, WorkspaceRole::Owner);
        $ownSite = Site::factory()->for($workspace)->create();
        $foreignSite = Site::factory()->create();

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get("/_authorized-sites/{$ownSite->public_id}")
            ->assertOk();

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get("/_authorized-sites/{$foreignSite->public_id}")
            ->assertForbidden();
    }

    public function test_site_policy_delegates_to_permission_foundation_without_role_checks(): void
    {
        $policy = file_get_contents(app_path('Policies/SitePolicy.php'));

        $this->assertIsString($policy);
        $this->assertStringContainsString('WorkspaceAuthorization', $policy);
        $this->assertStringNotContainsString('WorkspaceRole', $policy);
        $this->assertStringNotContainsString('membership->role', $policy);
    }

    private function resolveContext(User $user, ?Workspace $workspace = null): void
    {
        $this->actingAs($user);
        $session = app('session')->driver();

        if ($workspace !== null) {
            $session->put(WorkspaceContext::SESSION_KEY, $workspace->public_id);
        }

        app(WorkspaceContext::class)->resolve($user, $session);
    }

    private function workspaceFor(User $user, WorkspaceRole $role): Workspace
    {
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, $role);

        return $workspace;
    }
}
