<?php

namespace Tests\Feature\Team;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsWorkspaceTeam;
use Tests\TestCase;

class WorkspaceMemberLifecycleTest extends TestCase
{
    use BuildsWorkspaceTeam, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->buildTeamWorkspace(maxMembers: 3);
    }

    public function test_owner_suspends_and_reactivates_a_member(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->actingAs($this->owner)
            ->post(route('workspace.team.members.suspend', $designer->public_id))
            ->assertRedirect(route('workspace.team.index'))
            ->assertInertiaFlash('toast.type', 'success');
        $this->assertSame(WorkspaceMemberStatus::Suspended, $designer->refresh()->status);

        $this->actingAs($this->owner)
            ->post(route('workspace.team.members.reactivate', $designer->public_id))
            ->assertRedirect(route('workspace.team.index'));
        $this->assertSame(WorkspaceMemberStatus::Active, $designer->refresh()->status);
    }

    public function test_admin_manages_lower_roles_only(): void
    {
        $admin = $this->teamMember(WorkspaceRole::Admin);
        $otherAdmin = $this->teamMember(WorkspaceRole::Admin);
        $editor = $this->teamMember(WorkspaceRole::ContentEditor);
        $ownerMember = $this->workspace->members()->where('user_id', $this->owner->id)->sole();

        $this->actingAs($admin->user)->post(route('workspace.team.members.suspend', $editor->public_id))->assertRedirect();
        $this->assertSame(WorkspaceMemberStatus::Suspended, $editor->refresh()->status);

        $this->actingAs($admin->user)->post(route('workspace.team.members.suspend', $otherAdmin->public_id))->assertForbidden();
        $this->actingAs($admin->user)->delete(route('workspace.team.members.destroy', $otherAdmin->public_id))->assertForbidden();
        $this->actingAs($admin->user)->post(route('workspace.team.members.suspend', $ownerMember->public_id))->assertForbidden();
        $this->assertSame(WorkspaceMemberStatus::Active, $otherAdmin->refresh()->status);

        $this->actingAs($admin->user)->get(route('workspace.team.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.0.can_manage', false)
                ->where('members.1.can_manage', false)
                ->where('members.2.can_manage', false)
                ->where('members.3.can_manage', true));
    }

    public function test_nobody_suspends_or_removes_the_owner_or_themselves(): void
    {
        $ownerMember = $this->workspace->members()->where('user_id', $this->owner->id)->sole();
        $secondOwner = $this->teamMember(WorkspaceRole::Owner);
        $admin = $this->teamMember(WorkspaceRole::Admin);

        $this->actingAs($this->owner)->post(route('workspace.team.members.suspend', $secondOwner->public_id))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('workspace.team.members.destroy', $secondOwner->public_id))->assertForbidden();
        $this->actingAs($this->owner)->post(route('workspace.team.members.suspend', $ownerMember->public_id))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('workspace.team.members.destroy', $ownerMember->public_id))->assertForbidden();
        $this->actingAs($admin->user)->post(route('workspace.team.members.suspend', $admin->public_id))->assertForbidden();
        $this->actingAs($admin->user)->delete(route('workspace.team.members.destroy', $admin->public_id))->assertForbidden();

        $this->assertSame(3, $this->workspace->members()->where('status', 'active')->count());
    }

    public function test_members_without_manage_members_or_of_other_workspaces_cannot_act(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);
        $editor = $this->teamMember(WorkspaceRole::ContentEditor);
        $foreign = $this->teamMember(WorkspaceRole::Designer, workspace: Workspace::factory()->create());

        $this->actingAs($designer->user)->post(route('workspace.team.members.suspend', $editor->public_id))->assertForbidden();
        $this->actingAs($this->owner)->post(route('workspace.team.members.suspend', $foreign->public_id))->assertNotFound();
        $this->actingAs($this->owner)->delete(route('workspace.team.members.destroy', $foreign->public_id))->assertNotFound();

        $this->assertSame(WorkspaceMemberStatus::Active, $foreign->refresh()->status);
    }

    public function test_suspended_member_loses_the_workspace_on_next_request_but_keeps_others(): void
    {
        $user = User::factory()->create();
        $home = Workspace::factory()->create(['name' => 'Личное']);
        $home->addMember($user, WorkspaceRole::Owner);
        $designer = $this->teamMember(WorkspaceRole::Designer, user: $user);
        $site = Site::factory()->for($this->workspace)->create();

        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id])
            ->get(route('sites.show', $site))->assertOk();

        $this->actingAs($this->owner)->post(route('workspace.team.members.suspend', $designer->public_id));

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSessionHas(WorkspaceContext::SESSION_KEY, $home->public_id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current.public_id', $home->public_id)
                ->has('workspace.available', 1));
        $this->actingAs($user)->get(route('sites.show', $site))->assertNotFound();
        $this->actingAs($user)->post(route('workspace.switch', $this->workspace->public_id))->assertNotFound();
    }

    public function test_suspension_keeps_the_seat_and_reactivation_needs_no_new_seat(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);
        $this->teamMember(WorkspaceRole::ContentEditor);

        $this->actingAs($this->owner)->post(route('workspace.team.members.suspend', $designer->public_id));
        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), ['email' => 'x@example.com', 'role' => 'designer'])
            ->assertSessionHasErrors('email');

        $this->setMaxMembers(1);
        $this->actingAs($this->owner)->post(route('workspace.team.members.reactivate', $designer->public_id))->assertSessionHasNoErrors();
        $this->assertSame(WorkspaceMemberStatus::Active, $designer->refresh()->status);
    }

    public function test_reactivation_requires_a_suspended_member(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->actingAs($this->owner)
            ->post(route('workspace.team.members.reactivate', $designer->public_id))
            ->assertSessionHasErrors('member');
    }

    public function test_removal_deletes_only_the_membership_frees_the_seat_and_allows_reinvite(): void
    {
        $user = User::factory()->create(['email' => 'leaver@example.com']);
        $home = Workspace::factory()->create();
        $home->addMember($user, WorkspaceRole::Owner);
        $designer = $this->teamMember(WorkspaceRole::Designer, user: $user);
        $this->teamMember(WorkspaceRole::ContentEditor);

        $this->actingAs($this->owner)
            ->delete(route('workspace.team.members.destroy', $designer->public_id))
            ->assertRedirect(route('workspace.team.index'));

        $this->assertNull(WorkspaceMember::query()->find($designer->id));
        $this->assertNotNull(User::query()->find($user->id));
        $this->assertTrue($home->members()->where('user_id', $user->id)->exists());

        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), ['email' => 'leaver@example.com', 'role' => 'designer'])
            ->assertSessionHasNoErrors();
    }

    public function test_removed_member_session_no_longer_grants_access(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);
        $site = Site::factory()->for($this->workspace)->create();
        $session = [WorkspaceContext::SESSION_KEY => $this->workspace->public_id];

        $this->actingAs($designer->user)->withSession($session)->get(route('sites.show', $site))->assertOk();

        $this->actingAs($this->owner)->delete(route('workspace.team.members.destroy', $designer->public_id));

        $this->actingAs($designer->user)->withSession($session)->get(route('sites.show', $site))->assertRedirect();
        $this->actingAs($designer->user)->withSession($session)->get(route('dashboard'))->assertRedirect();
        $this->assertNotSame($this->workspace->public_id, session(WorkspaceContext::SESSION_KEY));
    }
}
