<?php

namespace Tests\Feature\Sites;

use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreateSitePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_only_official_templates_with_safe_fields(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 2);
        $starter = Template::factory()->create(['name' => 'Стартовый шаблон']);
        $blank = Template::factory()->create(['name' => 'Пустой шаблон']);
        Template::factory()->create(['name' => 'Скрытый шаблон', 'is_official' => false]);

        $response = $this->createPage($user, $workspace);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('sites/create')
            ->where('currentWorkspace', [
                'public_id' => $workspace->public_id,
                'name' => $workspace->name,
            ])
            ->has('templates', 2)
            ->where('templates.0', [
                'public_id' => $blank->public_id,
                'name' => 'Пустой шаблон',
                'site_types' => ['multi_page', 'landing'],
            ])
            ->where('templates.1', [
                'public_id' => $starter->public_id,
                'name' => 'Стартовый шаблон',
                'site_types' => ['multi_page', 'landing'],
            ])
            ->missing('templates.0.id')
            ->missing('currentWorkspace.id')
            ->where('siteLimit', [
                'active' => 0,
                'max' => 2,
                'reached' => false,
            ]));
        $response->assertDontSee('Скрытый шаблон');
    }

    public function test_reached_active_site_limit_is_backend_derived(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 1);
        Site::factory()->for($workspace)->create();
        Site::factory()->for($workspace)->archived()->create();
        Site::factory()->create();

        $this->createPage($user, $workspace)
            ->assertInertia(fn (Assert $page) => $page
                ->where('siteLimit', [
                    'active' => 1,
                    'max' => 1,
                    'reached' => true,
                ]));
    }

    public function test_user_without_create_sites_permission_is_forbidden(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Designer, 2);

        $this->createPage($user, $workspace)->assertForbidden();
    }

    public function test_guest_and_unverified_user_cannot_open_wizard(): void
    {
        $this->get(route('sites.create'))->assertRedirect(route('login'));

        $user = User::factory()->unverified()->create();
        $workspace = $this->workspaceWithLimit(1);
        $workspace->addMember($user, WorkspaceRole::Owner);

        $this->createPage($user, $workspace)->assertRedirect(route('verification.notice'));
    }

    /**
     * @return array{User, Workspace}
     */
    private function userWithWorkspace(WorkspaceRole $role, int $maxSites): array
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithLimit($maxSites);
        $workspace->addMember($user, $role);

        return [$user, $workspace];
    }

    private function workspaceWithLimit(int $maxSites): Workspace
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, $maxSites);

        return Workspace::factory()->create(['plan_id' => $plan->id]);
    }

    private function createPage(User $user, Workspace $workspace): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.create'));
    }
}
