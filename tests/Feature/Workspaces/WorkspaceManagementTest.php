<?php

namespace Tests\Feature\Workspaces;

use App\Enums\Entitlement;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use App\Support\WorkspaceEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_workspace_names_are_not_changed_by_new_accounts(): void
    {
        $existing = Workspace::factory()->create(['name' => 'Иван Петров']);

        $this->post(route('register.store'), [
            'name' => 'Иван Петров',
            'email' => 'ivan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Иван Петров', $existing->refresh()->name);
        $this->assertSame(1, Workspace::query()->where('name', Workspace::DEFAULT_NAME)->count());
    }

    public function test_switcher_props_list_a_single_workspace(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace('Единственное');

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current.public_id', $workspace->public_id)
                ->has('workspace.available', 1)
                ->where('workspace.available.0.name', 'Единственное')
                ->missing('workspace.available.0.id'));
    }

    public function test_verified_user_creates_a_workspace_that_becomes_current_on_the_free_plan(): void
    {
        [$user, $first] = $this->ownerWithWorkspace('Первое');

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $first->public_id])
            ->get(route('workspaces.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('workspaces/create'));

        $response = $this->post(route('workspaces.store'), ['name' => '  АвтоГрупп Ростов  ']);

        $created = Workspace::query()->where('name', 'АвтоГрупп Ростов')->sole();
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas(WorkspaceContext::SESSION_KEY, $created->public_id);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $created->public_id);
        $this->assertSame(Plan::FREE_KEY, $created->plan?->key);
        $this->assertSame(2, app(WorkspaceEntitlements::class)->limit($created, Entitlement::MaxSites));

        $membership = $user->memberships()->where('workspace_id', $created->id)->sole();
        $this->assertSame(WorkspaceRole::Owner, $membership->role);
        $this->assertSame(WorkspaceMemberStatus::Active, $membership->status);

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.current.public_id', $created->public_id)
                ->has('workspace.available', 2));
    }

    public function test_workspace_creation_validates_the_name(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace('Первое');
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);

        foreach (['', '   ', str_repeat('а', Workspace::NAME_MAX_LENGTH + 1), '<b>Жирное</b>', "Строка\nвторая"] as $name) {
            $this->post(route('workspaces.store'), ['name' => $name])->assertSessionHasErrors('name');
        }

        $this->assertSame(1, Workspace::query()->count());
    }

    public function test_unverified_user_cannot_create_a_workspace(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('workspaces.store'), ['name' => 'Новое'])
            ->assertRedirect(route('verification.notice'));
        $this->assertSame(0, Workspace::query()->count());
    }

    public function test_foreign_workspace_cannot_be_switched_to_or_managed(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace('Моё');
        $foreign = Workspace::factory()->create(['name' => 'Чужое']);

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->post(route('workspace.switch', $foreign->public_id))
            ->assertNotFound();

        $this->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->patch(route('workspace.settings.update'), ['name' => 'Взлом'])
            ->assertRedirect(route('workspace.settings.edit'));

        $this->assertSame('Чужое', $foreign->refresh()->name);
        $this->assertSame('Взлом', $workspace->refresh()->name);
    }

    public function test_owner_sees_and_renames_the_current_workspace(): void
    {
        [$user, $workspace] = $this->ownerWithWorkspace('Старое имя');

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('workspace.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('workspaces/settings')
                ->where('settings.name', 'Старое имя')
                ->missing('settings.id')
                ->missing('settings.public_id'));

        $this->patch(route('workspace.settings.update'), ['name' => 'Новое имя'])
            ->assertRedirect(route('workspace.settings.edit'))
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertSame('Новое имя', $workspace->refresh()->name);
    }

    public function test_members_without_edit_workspace_cannot_manage_the_workspace(): void
    {
        $workspace = Workspace::factory()->create(['name' => 'Компания']);

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = User::factory()->create();
            $workspace->addMember($member, $role);

            $this->actingAs($member)
                ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
                ->get(route('workspace.settings.edit'))
                ->assertForbidden();

            $this->patch(route('workspace.settings.update'), ['name' => 'Чужое имя'])->assertForbidden();
        }

        $this->assertSame('Компания', $workspace->refresh()->name);
    }

    /**
     * @return array{User, Workspace}
     */
    private function ownerWithWorkspace(string $name): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['name' => $name]);
        $workspace->addMember($user, WorkspaceRole::Owner);

        return [$user, $workspace];
    }
}
