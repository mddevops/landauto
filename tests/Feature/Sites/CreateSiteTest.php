<?php

namespace Tests\Feature\Sites;

use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
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
        $template = Template::factory()->published()->create();

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
        $template = Template::factory()->published()->create();

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
        $template = Template::factory()->published()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Запрещённый сайт',
            'template' => $template->public_id,
        ])->assertRedirect(route('home'));

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_zero_or_reached_active_site_limit_denies_creation_without_partial_site(): void
    {
        $template = Template::factory()->published()->create();
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
        $template = Template::factory()->published()->create();

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
        $template = Template::factory()->published()->create();

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
        $template = Template::factory()->published()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => '',
            'template' => $template->public_id,
        ])->assertSessionHasErrors(['name' => 'Укажите название сайта.']);

        $this->assertDatabaseCount('sites', 0);
    }

    public function test_multi_page_requires_the_typed_entitlement_and_landing_does_not(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 5);

        $this->postAsCurrent($user, $workspace, ['name' => 'Многостраничный', 'site_type' => SiteType::MultiPage->value])
            ->assertSessionHasErrors(['site_type' => 'Многостраничные сайты недоступны на текущем тарифе. Выберите лендинг или смените тариф.']);
        $this->assertDatabaseCount('sites', 0);

        $this->postAsCurrent($user, $workspace, ['name' => 'Лендинг'])->assertSessionHasNoErrors();
        $this->assertSame(SiteType::Landing, Site::query()->sole()->site_type);

        [$paidUser, $paidWorkspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 5, multiPage: true);
        $this->postAsCurrent($paidUser, $paidWorkspace, ['name' => 'Многостраничный', 'site_type' => SiteType::MultiPage->value])
            ->assertSessionHasNoErrors();
        $site = $paidWorkspace->sites()->sole();
        $this->assertSame(SiteType::MultiPage, $site->site_type);
        $this->assertTrue($site->pages()->sole()->is_home);
    }

    public function test_quiz_and_chat_require_a_compatible_template_and_never_start_blank(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 10);
        $quiz = Template::factory()->forSiteTypes(SiteType::Quiz)->published()->create();
        $chat = Template::factory()->forSiteTypes(SiteType::ChatSelection)->published()->create();

        foreach ([SiteType::Quiz, SiteType::ChatSelection] as $type) {
            $this->postAsCurrent($user, $workspace, ['name' => 'Пустой', 'site_type' => $type->value])
                ->assertSessionHasErrors(['template' => 'Этот формат создаётся только из шаблона. Выберите шаблон.']);
        }

        $this->postAsCurrent($user, $workspace, ['name' => 'Квиз из чата', 'site_type' => SiteType::Quiz->value, 'template' => $chat->public_id])
            ->assertSessionHasErrors(['template' => 'Шаблон не подходит для выбранного формата сайта.']);
        $this->postAsCurrent($user, $workspace, ['name' => 'Лендинг из квиза', 'template' => $quiz->public_id])
            ->assertSessionHasErrors('template');
        $this->assertDatabaseCount('sites', 0);

        $this->postAsCurrent($user, $workspace, ['name' => 'Квиз', 'site_type' => SiteType::Quiz->value, 'template' => $quiz->public_id])
            ->assertSessionHasNoErrors();
        $this->postAsCurrent($user, $workspace, ['name' => 'Чат', 'site_type' => SiteType::ChatSelection->value, 'template' => $chat->public_id])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [SiteType::Quiz->value, SiteType::ChatSelection->value],
            $workspace->sites()->get()->map(fn (Site $site): string => $site->site_type->value)->all(),
        );
    }

    public function test_every_site_type_counts_toward_max_sites(): void
    {
        $quiz = Template::factory()->forSiteTypes(SiteType::Quiz)->published()->create();
        $chat = Template::factory()->forSiteTypes(SiteType::ChatSelection)->published()->create();
        $requests = [
            ['site_type' => SiteType::MultiPage->value],
            ['site_type' => SiteType::Landing->value],
            ['site_type' => SiteType::Quiz->value, 'template' => $quiz->public_id],
            ['site_type' => SiteType::ChatSelection->value, 'template' => $chat->public_id],
        ];

        foreach ($requests as $request) {
            [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 1, multiPage: true);
            Site::factory()->for($workspace)->create();

            $this->postAsCurrent($user, $workspace, ['name' => 'Сверх лимита', ...$request])->assertSessionHasErrors('site');
            $this->assertSame(1, $workspace->sites()->count(), $request['site_type']);
        }
    }

    public function test_unknown_type_or_start_is_rejected_and_create_page_exposes_safe_options(): void
    {
        [$user, $workspace] = $this->userWithWorkspace(WorkspaceRole::Owner, 3);
        Template::factory()->forSiteTypes(SiteType::Quiz)->published()->create(['name' => 'Квиз-шаблон']);
        Template::factory()->forSiteTypes()->published()->create(['name' => 'Без формата']);

        $this->postAsCurrent($user, $workspace, ['name' => 'Сайт', 'site_type' => 'shop'])->assertSessionHasErrors('site_type');
        $this->postAsCurrent($user, $workspace, ['name' => 'Сайт', 'start' => 'copy'])->assertSessionHasErrors('start');
        $this->assertDatabaseCount('sites', 0);

        $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->get(route('sites.create'))
            ->assertInertia(fn ($page) => $page
                ->where('siteTypes.0.value', 'multi_page')
                ->where('siteTypes.0.allowed', false)
                ->where('siteTypes.1.allowed', true)
                ->where('siteTypes.2.blank_allowed', false)
                ->has('templates', 1)
                ->where('templates.0.name', 'Квиз-шаблон')
                ->where('templates.0.site_types', ['quiz'])
                ->missing('templates.0.id'));
    }

    public function test_unverified_user_cannot_create_site(): void
    {
        $user = User::factory()->unverified()->create();
        $workspace = $this->workspaceWithLimit(1);
        $workspace->addMember($user, WorkspaceRole::Owner);
        $template = Template::factory()->published()->create();

        $this->postAsCurrent($user, $workspace, [
            'name' => 'Не создан',
            'template' => $template->public_id,
        ])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('sites', 0);
    }

    /**
     * @return array{User, Workspace}
     */
    private function userWithWorkspace(WorkspaceRole $role, int $maxSites, bool $multiPage = false): array
    {
        $user = User::factory()->create();
        $workspace = $this->workspaceWithLimit($maxSites, $multiPage);
        $workspace->addMember($user, $role);

        return [$user, $workspace];
    }

    private function workspaceWithLimit(int $maxSites, bool $multiPage = false): Workspace
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, $maxSites);

        if ($multiPage) {
            $plan->setEntitlement(Entitlement::MultiPageSites, true);
        }

        return Workspace::factory()->create(['plan_id' => $plan->id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postAsCurrent(User $user, Workspace $workspace, array $data): TestResponse
    {
        $defaults = ['site_type' => SiteType::Landing->value, 'start' => array_key_exists('template', $data) ? 'template' : 'blank'];

        return $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])
            ->post(route('sites.store'), [...$defaults, ...$data]);
    }
}
