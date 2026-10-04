<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use App\Support\WorkspacePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspacePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_receives_the_complete_workspace_permission_catalog(): void
    {
        $permissions = $this->resolver()->forRole(WorkspaceRole::Owner);

        $this->assertSame(WorkspacePermission::cases(), $permissions);
        $this->assertContains(WorkspacePermission::PublishSite, $permissions);
    }

    public function test_admin_receives_approved_permissions_without_owner_only_capabilities(): void
    {
        $permissions = $this->resolver()->forRole(WorkspaceRole::Admin);

        $this->assertSame([
            WorkspacePermission::ManageMembers,
            WorkspacePermission::CreateSites,
            WorkspacePermission::DeleteSite,
            WorkspacePermission::EditDesign,
            WorkspacePermission::EditContent,
            WorkspacePermission::EditForms,
            WorkspacePermission::EditVehicles,
            WorkspacePermission::EditPrices,
            WorkspacePermission::ManageIntegrations,
            WorkspacePermission::ViewSubmissions,
            WorkspacePermission::EditSeo,
            WorkspacePermission::ManageDomains,
            WorkspacePermission::PreviewSite,
            WorkspacePermission::PublishSite,
        ], $permissions);
        $this->assertContains(WorkspacePermission::ManageMembers, $permissions);
        $this->assertContains(WorkspacePermission::PublishSite, $permissions);
        $this->assertNotContains(WorkspacePermission::ManageBilling, $permissions);
        $this->assertNotContains(WorkspacePermission::TransferWorkspaceOwnership, $permissions);
        $this->assertNotContains(WorkspacePermission::ExportSubmissions, $permissions);
    }

    public function test_designer_permissions_do_not_imply_publishing_or_prices(): void
    {
        $permissions = $this->resolver()->forRole(WorkspaceRole::Designer);

        $this->assertContains(WorkspacePermission::EditDesign, $permissions);
        $this->assertContains(WorkspacePermission::EditContent, $permissions);
        $this->assertContains(WorkspacePermission::PreviewSite, $permissions);
        $this->assertNotContains(WorkspacePermission::PublishSite, $permissions);
        $this->assertNotContains(WorkspacePermission::EditPrices, $permissions);
    }

    public function test_content_editor_receives_only_content_permissions(): void
    {
        $this->assertSame([
            WorkspacePermission::ViewSite,
            WorkspacePermission::EditContent,
            WorkspacePermission::EditText,
            WorkspacePermission::EditImages,
            WorkspacePermission::EditSeoBasic,
        ], $this->resolver()->forRole(WorkspaceRole::ContentEditor));
    }

    public function test_suspended_membership_grants_no_permissions(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Owner, WorkspaceMemberStatus::Suspended);

        $this->resolveContext($user);

        $this->assertFalse(Gate::forUser($user)->allows(WorkspacePermission::ViewWorkspace->value));
        $this->assertFalse(Gate::forUser($user)->allows(WorkspacePermission::PublishSite->value));
    }

    public function test_foreign_user_cannot_use_current_workspace_permissions(): void
    {
        $member = User::factory()->create();
        $foreignUser = User::factory()->create();
        $this->workspaceFor($member, WorkspaceRole::Owner);
        $this->resolveContext($member);

        $this->assertTrue(Gate::forUser($member)->allows(WorkspacePermission::PublishSite->value));
        $this->assertFalse(Gate::forUser($foreignUser)->allows(WorkspacePermission::PublishSite->value));
    }

    public function test_permissions_are_isolated_to_the_selected_workspace(): void
    {
        $user = User::factory()->create();
        $ownerWorkspace = $this->workspaceFor($user, WorkspaceRole::Owner);
        $designerWorkspace = $this->workspaceFor($user, WorkspaceRole::Designer);

        $this->resolveContext($user, $ownerWorkspace);
        $this->assertTrue(Gate::forUser($user)->allows(WorkspacePermission::PublishSite->value));

        $this->resolveContext($user, $designerWorkspace);
        $this->assertFalse(Gate::forUser($user)->allows(WorkspacePermission::PublishSite->value));
        $this->assertTrue(Gate::forUser($user)->allows(WorkspacePermission::EditDesign->value));
    }

    public function test_backend_gate_denies_a_missing_permission(): void
    {
        Route::middleware(['web', 'auth', 'can:publish_site'])
            ->get('/_permission-probe', fn () => response()->noContent());

        $user = User::factory()->create();
        $this->workspaceFor($user, WorkspaceRole::Designer);

        $this->actingAs($user)->get('/_permission-probe')->assertForbidden();
    }

    public function test_shared_props_expose_only_safe_permission_keys_for_current_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, WorkspaceRole::Designer);
        $foreignWorkspace = $this->workspaceFor(User::factory()->create(), WorkspaceRole::Owner);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('workspace.current.public_id', $workspace->public_id)
            ->where('workspace.permissions', [
                WorkspacePermission::ViewSite->value,
                WorkspacePermission::EditDesign->value,
                WorkspacePermission::EditContent->value,
                WorkspacePermission::ManageAssets->value,
                WorkspacePermission::EditPopups->value,
                WorkspacePermission::PreviewSite->value,
            ])
            ->missing('workspace.role')
            ->missing('workspace.current.id')
            ->missing('workspace.current.membership'));
        $response->assertDontSee($foreignWorkspace->public_id);
    }

    private function resolver(): WorkspacePermissionResolver
    {
        return app(WorkspacePermissionResolver::class);
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
