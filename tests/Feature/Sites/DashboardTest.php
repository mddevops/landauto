<?php

namespace Tests\Feature\Sites;

use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_only_safe_sites_from_current_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, WorkspaceRole::Owner, 'Текущий Workspace');
        $foreignWorkspace = Workspace::factory()->create(['name' => 'Чужой Workspace']);
        $active = Site::factory()->for($workspace)->create(['name' => 'Активный сайт']);
        $archived = Site::factory()->for($workspace)->archived()->create(['name' => 'Архивный сайт']);
        $foreign = Site::factory()->for($foreignWorkspace)->create(['name' => 'Секретный сайт']);

        $response = $this->dashboard($user, $workspace);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('currentWorkspace', [
                'public_id' => $workspace->public_id,
                'name' => 'Текущий Workspace',
            ])
            ->missing('currentWorkspace.id')
            ->has('sites', 2)
            ->where('sites.0', [
                'public_id' => $active->public_id,
                'name' => 'Активный сайт',
                'status' => 'active',
                'address' => null,
            ])
            ->where('sites.1', [
                'public_id' => $archived->public_id,
                'name' => 'Архивный сайт',
                'status' => 'archived',
                'address' => null,
            ])
            ->missing('sites.0.id')
            ->missing('sites.0.workspace_id'));
        $response->assertDontSee($foreign->public_id);
        $response->assertDontSee('Секретный сайт');
    }

    public function test_dashboard_has_an_empty_site_collection_for_current_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceFor($user, WorkspaceRole::Owner, 'Пустой Workspace');
        Site::factory()->create(['name' => 'Чужой сайт']);

        $this->dashboard($user, $workspace)
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentWorkspace.name', 'Пустой Workspace')
                ->has('sites', 0)
                ->where('canViewSites', true));
    }

    public function test_create_site_cta_permission_and_limit_props_are_backend_derived(): void
    {
        $owner = User::factory()->create();
        $ownerWorkspace = $this->workspaceFor($owner, WorkspaceRole::Owner, 'Владелец', 1);
        Site::factory()->for($ownerWorkspace)->create();

        $this->dashboard($owner, $ownerWorkspace)
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreateSites', true)
                ->where('siteLimit', [
                    'active' => 1,
                    'max' => 1,
                    'reached' => true,
                ]));

        $designer = User::factory()->create();
        $designerWorkspace = $this->workspaceFor($designer, WorkspaceRole::Designer, 'Дизайнер', 5);

        $this->dashboard($designer, $designerWorkspace)
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreateSites', false)
                ->where('canViewSites', true));
    }

    private function workspaceFor(
        User $user,
        WorkspaceRole $role,
        string $name,
        int $maxSites = 3,
    ): Workspace {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, $maxSites);
        $workspace = Workspace::factory()->create([
            'name' => $name,
            'plan_id' => $plan->id,
        ]);
        $workspace->addMember($user, $role);

        return $workspace;
    }

    private function dashboard(User $user, Workspace $workspace): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('dashboard'));
    }
}
