<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountWorkspaceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_removes_its_empty_personal_workspace_and_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Owner);

        DB::table('sessions')->insert([
            'id' => 'another-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
    }

    public function test_account_deletion_removes_membership_when_another_active_owner_exists(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Owner);
        $workspace->addMember($otherOwner, WorkspaceRole::Owner);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $otherOwner->id,
            'role' => WorkspaceRole::Owner->value,
        ]);
    }

    public function test_account_deletion_is_blocked_for_the_only_owner_of_a_workspace_with_other_members(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->addMember($user, WorkspaceRole::Owner);
        $workspace->addMember($member, WorkspaceRole::Admin);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('account')
            ->assertRedirect(route('profile.edit'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceRole::Owner->value,
        ]);
    }
}
