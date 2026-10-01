<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_and_selects_only_their_active_workspaces(): void
    {
        $user = User::factory()->create();
        $first = $this->workspaceFor($user, 'Первый');
        $second = $this->workspaceFor($user, 'Второй');

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->where('workspace.current.public_id', $first->public_id)
                ->where('workspace.current.name', 'Первый')
                ->has('workspace.available', 2)
                ->where('workspace.available.0.public_id', $first->public_id)
                ->where('workspace.available.1.public_id', $second->public_id)
                ->missing('workspace.current.id'),
        );

        $this->actingAs($user)
            ->post(route('workspace.switch', $second->public_id))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(WorkspaceContext::SESSION_KEY, $second->public_id);

        $this->assertSame(2, Workspace::query()->count());
        $this->assertSame(2, WorkspaceMember::query()->where('user_id', $user->id)->count());
    }

    public function test_user_cannot_select_another_users_workspace(): void
    {
        $user = User::factory()->create();
        $own = $this->workspaceFor($user, 'Свой');
        $foreign = $this->workspaceFor(User::factory()->create(), 'Чужой');

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $own->public_id])
            ->post(route('workspace.switch', $foreign->public_id))
            ->assertNotFound()
            ->assertSessionHas(WorkspaceContext::SESSION_KEY, $own->public_id);
    }

    public function test_suspended_membership_and_workspace_do_not_grant_context(): void
    {
        $user = User::factory()->create();
        $active = $this->workspaceFor($user, 'Активный');
        $suspendedMembershipWorkspace = Workspace::factory()->create(['name' => 'Приостановленное участие']);
        $suspendedMembershipWorkspace->addMember(
            $user,
            WorkspaceRole::Admin,
            WorkspaceMemberStatus::Suspended,
        );
        $suspendedWorkspace = Workspace::factory()->suspended()->create(['name' => 'Приостановленный Workspace']);
        $suspendedWorkspace->addMember($user, WorkspaceRole::Admin);

        foreach ([$suspendedMembershipWorkspace, $suspendedWorkspace] as $unavailable) {
            $this->actingAs($user)
                ->post(route('workspace.switch', $unavailable->public_id))
                ->assertNotFound();
        }

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->where('workspace.current.public_id', $active->public_id)
                ->has('workspace.available', 1),
        );
    }

    public function test_invalid_and_nonexistent_public_ids_are_not_found(): void
    {
        $user = User::factory()->create();
        $this->workspaceFor($user, 'Свой');

        $this->actingAs($user)
            ->post('/workspaces/not-a-ulid/switch')
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('workspace.switch', (string) Str::ulid()))
            ->assertNotFound();
    }

    public function test_selected_workspace_persists_between_requests(): void
    {
        $user = User::factory()->create();
        $this->workspaceFor($user, 'Первый');
        $selected = $this->workspaceFor($user, 'Выбранный');

        $this->actingAs($user)->post(route('workspace.switch', $selected->public_id));

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(
            fn (Assert $page) => $page
                ->where('workspace.current.public_id', $selected->public_id)
                ->where('workspace.current.name', 'Выбранный'),
        );
    }

    public function test_context_falls_back_when_selected_membership_is_no_longer_active(): void
    {
        $user = User::factory()->create();
        $fallback = $this->workspaceFor($user, 'Доступный');
        $selected = $this->workspaceFor($user, 'Больше недоступный', WorkspaceRole::Admin);
        $membership = $user->activeMembershipIn($selected);

        $this->actingAs($user)->post(route('workspace.switch', $selected->public_id));

        $membership?->update(['status' => WorkspaceMemberStatus::Suspended]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSessionHas(WorkspaceContext::SESSION_KEY, $fallback->public_id)
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('workspace.current.public_id', $fallback->public_id)
                    ->has('workspace.available', 1),
            );
    }

    public function test_foreign_workspace_is_not_leaked_through_shared_props(): void
    {
        $user = User::factory()->create();
        $own = $this->workspaceFor($user, 'Свой Workspace');
        $foreign = $this->workspaceFor(User::factory()->create(), 'Секретный чужой Workspace');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('workspace.current.public_id', $own->public_id)
                ->has('workspace.available', 1),
        );
        $response->assertDontSee($foreign->public_id);
        $response->assertDontSee('Секретный чужой Workspace');
    }

    public function test_user_without_an_active_workspace_is_redirected_from_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => (string) Str::ulid()])
            ->get(route('dashboard'))
            ->assertRedirect(route('home'))
            ->assertSessionMissing(WorkspaceContext::SESSION_KEY);
    }

    private function workspaceFor(
        User $user,
        string $name,
        WorkspaceRole $role = WorkspaceRole::Owner,
    ): Workspace {
        $workspace = Workspace::factory()->create(['name' => $name]);
        $workspace->addMember($user, $role);

        return $workspace;
    }
}
