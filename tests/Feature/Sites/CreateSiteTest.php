<?php

namespace Tests\Feature\Sites;

use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CreateSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_site_in_current_workspace_from_official_template(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 2);
        $template = Template::factory()->create();

        $response = $this->postAsCurrent($user, $workspace, [
            'name' => 'Автосалон Север',
            'template' => $template->public_id,
        ]);

        $site = Site::query()->sole();

        $response->assertRedirect(route('dashboard', ['site' => $site->public_id]));
        $this->assertTrue($site->workspace->is($workspace));
        $this->assertSame('Автосалон Север', $site->name);
        $this->assertSame(SiteStatus::Active, $site->status);
        $this->assertTrue(Str::isUlid($site->public_id));
        $home = $site->pages()->sole();
        $this->assertTrue($home->is_home);
        $this->assertSame('Главная', $home->title);
        $this->assertSame('home', $home->slug);
        $location = $response->headers->get('Location');
        $this->assertIsString($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(['site' => $site->public_id], $query);
    }

    public function test_workspace_ownership_is_server_derived_and_request_cannot_replace_it(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 2);
        $foreignWorkspace = Workspace::factory()->create();
        $template = Template::factory()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Безопасный сайт',
            'template' => $template->public_id,
            'workspace_id' => $foreignWorkspace->id,
            'workspace_public_id' => $foreignWorkspace->public_id,
        ])->assertRedirect();

        $site = Site::query()->sole();

        $this->assertSame($workspace->id, $site->workspace_id);
        $this->assertNotSame($foreignWorkspace->id, $site->workspace_id);
    }

    public function test_user_without_create_sites_permission_is_denied_before_template_validation(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Designer, 2);

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Запрещённый сайт',
            'template' => 'not-a-template',
        ])->assertForbidden();

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_suspended_membership_cannot_create_site(): void
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithLimit(2);
        $workspace->addMember($user, WorkspaceRole::Owner, WorkspaceMemberStatus::Suspended);
        $template = Template::factory()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Запрещённый сайт',
            'template' => $template->public_id,
        ])->assertRedirect(route('home'));

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_zero_or_reached_active_site_limit_denies_creation_without_partial_site(): void
    {
        $template = Template::factory()->create();
        [$zeroLimitUser, $zeroLimitWorkspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 0);

        $this->postAsCurrent($zeroLimitUser, $zeroLimitWorkspace, [
            'name' => 'Нет слота',
            'template' => $template->public_id,
        ])->assertSessionHasErrors('site');
        $this->assertSame(0, $zeroLimitWorkspace->sites()->count());

        [$limitedUser, $limitedWorkspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 1);
        Site::factory()->for($limitedWorkspace)->create();

        $this->postAsCurrent($limitedUser, $limitedWorkspace, [
            'name' => 'Лишний сайт',
            'template' => $template->public_id,
        ])->assertSessionHasErrors('site');
        $this->assertSame(1, $limitedWorkspace->sites()->count());
    }

    public function test_archived_sites_do_not_consume_active_site_limit(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 1);
        Site::factory()->for($workspace)->archived()->create();
        $template = Template::factory()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Новый активный сайт',
            'template' => $template->public_id,
        ])->assertRedirect();

        $this->assertSame(1, $workspace->sites()->where('status', SiteStatus::Active->value)->count());
        $this->assertSame(2, $workspace->sites()->count());
    }

    public function test_sites_in_other_workspace_do_not_consume_current_workspace_limit(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 1);
        Site::factory()->create();
        $template = Template::factory()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Сайт текущего пространства',
            'template' => $template->public_id,
        ])->assertRedirect();

        $this->assertSame(1, $workspace->sites()->count());
    }

    public function test_template_must_use_available_public_identifier(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 3);
        $unavailable = Template::factory()->create(['is_official' => false]);

        foreach ([(string) Str::ulid(), (string) $unavailable->id, $unavailable->public_id] as $template) {
            $this->postAsCurrent($user, $workspace, [
                'name' => 'Не создан',
                'template' => $template,
            ])->assertSessionHasErrors('template');
        }

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_site_name_is_validated_server_side_in_russian(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 1);
        $template = Template::factory()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => '',
            'template' => $template->public_id,
        ])->assertSessionHasErrors(['name' => 'Укажите название сайта.']);

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_unverified_user_cannot_create_site(): void
    {
        $user = User::factory()->unverified()->create();
        $workspace = $this->workspaceWithLimit(1);
        $workspace->addMember($user, WorkspaceRole::Owner);
        $template = Template::factory()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Не создан',
            'template' => $template->public_id,
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('sites', 0);
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function postAsCurrent(User $user, Workspace $workspace, array $data): TestResponse
    {
        return $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->post(route('sites.store'), $data);
    }
}
