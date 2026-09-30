<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceStatus;
use App\Exceptions\LastWorkspaceOwnerException;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class WorkspaceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_gets_a_ulid_public_id_and_keeps_an_integer_key(): void
    {
        $workspace = Workspace::create(['name' => 'Автосалон']);

        $this->assertTrue(Str::isUlid($workspace->public_id));
        $this->assertSame('int', $workspace->getKeyType());
        $this->assertTrue($workspace->getIncrementing());
        $this->assertSame('int', (new WorkspaceMember)->getKeyType());
        $this->assertTrue((new WorkspaceMember)->getIncrementing());
        $this->assertSame('public_id', $workspace->getRouteKeyName());
        $this->assertSame($workspace->public_id, $workspace->getRouteKey());
        $this->assertSame(WorkspaceStatus::Active, $workspace->status);
    }

    public function test_public_id_is_immutable(): void
    {
        $workspace = Workspace::factory()->create();

        $this->expectException(LogicException::class);
        $workspace->forceFill(['public_id' => (string) Str::ulid()])->save();
    }

    public function test_ownership_and_identity_fields_are_not_mass_assignable(): void
    {
        $workspace = new Workspace(['name' => 'Автосалон', 'public_id' => 'forged', 'status' => 'suspended', 'id' => 5]);
        $member = new WorkspaceMember(['workspace_id' => 1, 'user_id' => 1, 'public_id' => 'forged', 'role' => 'admin']);

        $this->assertNull($workspace->getAttribute('public_id'));
        $this->assertNull($workspace->getAttribute('id'));
        $this->assertSame(WorkspaceStatus::Active, $workspace->status);
        $this->assertNull($member->getAttribute('workspace_id'));
        $this->assertNull($member->getAttribute('user_id'));
        $this->assertNull($member->getAttribute('public_id'));
    }

    public function test_serialization_exposes_public_ids_but_not_numeric_ids(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $workspace->addMember(User::factory()->create(), WorkspaceRole::Owner);

        $workspaceArray = $workspace->toArray();
        $memberArray = $member->toArray();

        $this->assertArrayHasKey('public_id', $workspaceArray);
        $this->assertArrayNotHasKey('id', $workspaceArray);
        $this->assertArrayHasKey('public_id', $memberArray);
        $this->assertArrayNotHasKey('id', $memberArray);
        $this->assertArrayNotHasKey('workspace_id', $memberArray);
        $this->assertArrayNotHasKey('user_id', $memberArray);
    }

    public function test_route_binding_resolves_by_public_id_only(): void
    {
        $workspace = Workspace::factory()->create();

        $this->assertTrue($workspace->is((new Workspace)->resolveRouteBinding($workspace->public_id)));
        $this->assertNull((new Workspace)->resolveRouteBinding((string) Str::ulid()));
    }

    public function test_add_member_creates_membership_from_server_context(): void
    {
        $workspace = Workspace::factory()->create();
        $user = User::factory()->create();

        $member = $workspace->addMember($user, WorkspaceRole::Owner);

        $this->assertTrue(Str::isUlid($member->public_id));
        $this->assertSame($workspace->id, $member->workspace_id);
        $this->assertSame($user->id, $member->user_id);
        $this->assertSame(WorkspaceRole::Owner, $member->role);
        $this->assertSame(WorkspaceMemberStatus::Active, $member->status);
        $this->assertNotNull($member->joined_at);
        $this->assertTrue($member->workspace->is($workspace));
        $this->assertTrue($member->user->is($user));
    }

    public function test_invited_member_has_no_joined_at(): void
    {
        $member = Workspace::factory()->create()
            ->addMember(User::factory()->create(), WorkspaceRole::Admin, WorkspaceMemberStatus::Invited);

        $this->assertNull($member->joined_at);
    }

    public function test_membership_workspace_and_user_cannot_be_reassigned(): void
    {
        $member = WorkspaceMember::factory()->create();

        $this->expectException(LogicException::class);
        $member->forceFill(['workspace_id' => Workspace::factory()->create()->id])->save();
    }

    public function test_user_relationships_distinguish_active_memberships(): void
    {
        $user = User::factory()->create();
        $active = Workspace::factory()->create();
        $invited = Workspace::factory()->create();
        $suspended = Workspace::factory()->create();
        $active->addMember($user, WorkspaceRole::Designer);
        $invited->addMember($user, WorkspaceRole::Admin, WorkspaceMemberStatus::Invited);
        $suspended->addMember($user, WorkspaceRole::Admin, WorkspaceMemberStatus::Suspended);

        $this->assertCount(3, $user->memberships);
        $this->assertCount(3, $user->workspaces);
        $this->assertEquals([$active->id], $user->activeWorkspaces()->pluck('workspaces.id')->all());
        $this->assertNotNull($user->activeMembershipIn($active));
        $this->assertNull($user->activeMembershipIn($invited));
        $this->assertNull($user->activeMembershipIn($suspended));
        $this->assertNull(User::factory()->create()->activeMembershipIn($active));
    }

    public function test_workspace_owner_semantics(): void
    {
        $workspace = Workspace::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $suspendedOwner = User::factory()->create();
        $workspace->addMember($owner, WorkspaceRole::Owner);
        $workspace->addMember($admin, WorkspaceRole::Admin);
        $workspace->addMember($suspendedOwner, WorkspaceRole::Owner, WorkspaceMemberStatus::Suspended);

        $this->assertTrue($workspace->isOwnedBy($owner));
        $this->assertFalse($workspace->isOwnedBy($admin));
        $this->assertFalse($workspace->isOwnedBy($suspendedOwner));
        $this->assertCount(1, $workspace->owners);
        $this->assertCount(3, $workspace->members);
        $this->assertCount(3, $workspace->users);
    }

    public function test_last_owner_cannot_be_demoted(): void
    {
        $member = Workspace::factory()->create()->addMember(User::factory()->create(), WorkspaceRole::Owner);

        $this->assertLastOwnerGuard(fn () => $member->update(['role' => WorkspaceRole::Admin]), $member);
    }

    public function test_last_owner_cannot_be_suspended(): void
    {
        $member = Workspace::factory()->create()->addMember(User::factory()->create(), WorkspaceRole::Owner);

        $this->assertLastOwnerGuard(fn () => $member->update(['status' => WorkspaceMemberStatus::Suspended]), $member);
    }

    public function test_last_owner_cannot_be_removed(): void
    {
        $member = Workspace::factory()->create()->addMember(User::factory()->create(), WorkspaceRole::Owner);

        $this->assertLastOwnerGuard(fn () => $member->delete(), $member);
    }

    public function test_suspended_owner_does_not_count_as_another_owner(): void
    {
        $workspace = Workspace::factory()->create();
        $member = $workspace->addMember(User::factory()->create(), WorkspaceRole::Owner);
        $workspace->addMember(User::factory()->create(), WorkspaceRole::Owner, WorkspaceMemberStatus::Suspended);

        $this->assertLastOwnerGuard(fn () => $member->delete(), $member);
    }

    public function test_owner_can_be_demoted_or_removed_when_another_active_owner_exists(): void
    {
        $workspace = Workspace::factory()->create();
        $first = $workspace->addMember(User::factory()->create(), WorkspaceRole::Owner);
        $second = $workspace->addMember(User::factory()->create(), WorkspaceRole::Owner);

        $first->update(['role' => WorkspaceRole::Admin]);
        $this->assertSame(WorkspaceRole::Admin, $first->fresh()?->role);

        $this->assertLastOwnerGuard(fn () => $second->delete(), $second);

        $first->update(['role' => WorkspaceRole::Owner]);
        $second->delete();
        $this->assertModelMissing($second);
        $this->assertTrue($workspace->isOwnedBy($first->user));
    }

    public function test_non_owner_members_can_be_changed_and_removed_freely(): void
    {
        $workspace = Workspace::factory()->create();
        $workspace->addMember(User::factory()->create(), WorkspaceRole::Owner);
        $admin = $workspace->addMember(User::factory()->create(), WorkspaceRole::Admin);

        $admin->update(['status' => WorkspaceMemberStatus::Suspended]);
        $admin->delete();

        $this->assertModelMissing($admin);
    }

    public function test_last_owner_message_is_russian(): void
    {
        $this->assertSame(
            'В рабочем пространстве должен остаться хотя бы один владелец.',
            (new LastWorkspaceOwnerException)->getMessage(),
        );
    }

    private function assertLastOwnerGuard(callable $action, WorkspaceMember $member): void
    {
        $original = $member->fresh();

        try {
            $action();
            $this->fail('The last active owner was changed.');
        } catch (LastWorkspaceOwnerException) {
        }

        $this->assertNotNull($original);
        $this->assertDatabaseHas('workspace_members', [
            'id' => $member->id,
            'role' => $original->role->value,
            'status' => $original->status->value,
        ]);
    }
}
