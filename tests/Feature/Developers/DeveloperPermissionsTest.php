<?php

namespace Tests\Feature\Developers;

use App\Developers\DeveloperAuthorization;
use App\Enums\DeveloperPermission;
use App\Enums\DeveloperProfileStatus;
use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Models\DeveloperProfile;
use App\Models\PlatformRoleAssignment;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PlatformAuthorization;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class DeveloperPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['create_blocks', 'create_templates', 'submit_marketplace_item'];

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);
    }

    public function test_permission_catalog_has_no_access_or_foreign_domain_keys(): void
    {
        $this->assertSame(self::ALL, array_map(fn (DeveloperPermission $permission): string => $permission->value, DeveloperPermission::cases()));
        $this->assertSame(DeveloperPermission::cases(), DeveloperPermission::defaults());

        foreach (['access_developer_platform', 'manage_billing', 'manage_users', 'manage_workspaces', 'manage_catalog', 'manage_marketplace', 'approve_marketplace_item'] as $key) {
            $this->assertNull(DeveloperPermission::tryFrom($key), $key);
        }
    }

    public function test_new_profile_receives_exactly_the_default_permissions(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->superAdmin)
            ->post(route('platform.developers.store'), ['email' => $target->email, 'display_name' => 'Студия', 'slug' => 'studio'])
            ->assertSessionHasNoErrors();

        $profile = DeveloperProfile::query()->sole();
        $this->assertSame(self::ALL, $this->storedKeys($profile));
        $this->assertSame(3, DB::table('developer_profile_permissions')->count());
    }

    public function test_migration_backfills_current_defaults_for_existing_profiles(): void
    {
        $profiles = DeveloperProfile::factory()->count(2)->create();
        DeveloperProfile::factory()->suspended()->create();
        Schema::drop('developer_profile_permissions');

        $migration = require database_path('migrations/2026_10_12_000002_create_developer_profile_permissions_table.php');
        $migration->up();

        foreach (DeveloperProfile::query()->get() as $profile) {
            $this->assertSame(self::ALL, $this->storedKeys($profile), $profile->slug);
        }

        // Rolling back and migrating again yields the same set without duplicates.
        $migration->down();
        $migration->up();
        $this->assertSame(9, DB::table('developer_profile_permissions')->count());
        $this->assertSame(self::ALL, $this->storedKeys($profiles->first()));
    }

    public function test_central_resolver_is_deny_by_default(): void
    {
        $authorization = app(DeveloperAuthorization::class);
        $full = DeveloperProfile::factory()->withPermissions()->create();
        $blocksOnly = DeveloperProfile::factory()->withPermissions(DeveloperPermission::CreateBlocks)->create();
        $none = DeveloperProfile::factory()->create();
        $suspended = DeveloperProfile::factory()->suspended()->withPermissions()->create();

        $this->assertTrue($authorization->allows($full, DeveloperPermission::CreateTemplates));
        $this->assertTrue($authorization->allowsUser($full->user, DeveloperPermission::SubmitMarketplaceItem));

        $this->assertTrue($authorization->allowsUser($blocksOnly->user, DeveloperPermission::CreateBlocks));
        $this->assertFalse($authorization->allowsUser($blocksOnly->user, DeveloperPermission::CreateTemplates));
        $this->assertSame([], $authorization->permissions($none));

        // Suspension keeps the stored grants but denies all of them.
        $this->assertSame(DeveloperPermission::cases(), $authorization->granted($suspended));
        $this->assertSame([], $authorization->permissions($suspended));
        foreach (DeveloperPermission::cases() as $permission) {
            $this->assertFalse($authorization->allows($suspended, $permission));
            $this->assertFalse($authorization->allowsUser($suspended->user, $permission));
            $this->assertFalse($authorization->allowsUser(null, $permission));
            $this->assertFalse($authorization->allowsUser($this->superAdmin, $permission), 'platform role');
        }

        // A Workspace Owner without a profile gets nothing either.
        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);
        $this->assertFalse($authorization->allowsUser($owner, DeveloperPermission::CreateBlocks));

        // An unknown stored key never grants anything.
        DB::table('developer_profile_permissions')->insert(['developer_profile_id' => $none->id, 'permission' => 'manage_billing', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame([], $authorization->permissions($none));
        $this->assertSame(array_fill_keys(self::ALL, false), $authorization->capabilities($none));
    }

    public function test_super_admin_manages_the_explicit_permission_set(): void
    {
        Log::spy();
        $profile = DeveloperProfile::factory()->withPermissions()->create(['display_name' => 'Студия']);

        $this->actingAs($this->superAdmin)->get(route('platform.developers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('developers.0.permissions', self::ALL)
                ->where('permissionOptions.1', ['value' => 'create_templates', 'label' => 'Создание шаблонов', 'short_label' => 'Шаблоны']));

        $this->updatePermissions($profile, ['create_blocks', 'submit_marketplace_item'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('platform.developers.index'))
            ->assertInertiaFlash('toast.message', 'Права разработчика «Студия» сохранены.');
        $this->assertSame(['create_blocks', 'submit_marketplace_item'], $this->storedKeys($profile));

        $this->updatePermissions($profile, ['create_templates', 'create_blocks', 'submit_marketplace_item'])->assertSessionHasNoErrors();
        $this->assertSame(self::ALL, $this->storedKeys($profile));

        $this->updatePermissions($profile, [])->assertSessionHasNoErrors();
        $this->assertSame([], $this->storedKeys($profile));
        $this->assertSame(DeveloperProfileStatus::Active, $profile->fresh()?->status, 'zero permissions never suspend');

        $this->updatePermissions($profile, self::ALL)->assertSessionHasNoErrors();
        $this->assertSame(self::ALL, $this->storedKeys($profile));

        Log::shouldHaveReceived('info')->with('developer.permissions_updated', Mockery::on(
            fn (array $context): bool => $context === ['developer_profile' => $profile->public_id, 'permissions' => [], 'actor_user_id' => $this->superAdmin->id],
        ))->once();
        Log::shouldHaveReceived('info')->with('developer.permissions_updated', Mockery::any())->times(4);

        // Saving the same set is a no-op without a log entry.
        $this->updatePermissions($profile, self::ALL)->assertSessionHasNoErrors();
        Log::shouldHaveReceived('info')->with('developer.permissions_updated', Mockery::any())->times(4);
    }

    public function test_permission_updates_reject_unknown_or_malformed_keys(): void
    {
        $profile = DeveloperProfile::factory()->withPermissions(DeveloperPermission::CreateBlocks)->create();

        foreach ([
            ['permissions' => ['manage_billing']],
            ['permissions' => ['access_developer_platform']],
            ['permissions' => ['create_blocks', 'create_blocks']],
            ['permissions' => 'create_blocks'],
            [],
        ] as $payload) {
            $this->actingAs($this->superAdmin)
                ->put(route('platform.developers.permissions.update', $profile), $payload)
                ->assertSessionHasErrors();
        }

        $this->updatePermissions($profile, ['approve_marketplace_item'])
            ->assertSessionHasErrors(['permissions.0' => 'Неизвестное право разработчика.']);
        $this->assertSame(['create_blocks'], $this->storedKeys($profile));
        $this->assertSame(0, DB::table('developer_profile_permissions')->whereNotIn('permission', self::ALL)->count());
    }

    public function test_only_manage_developers_may_change_permissions(): void
    {
        $profile = DeveloperProfile::factory()->withPermissions(DeveloperPermission::CreateBlocks)->create();
        $catalogManager = $this->userWithRole(PlatformRole::CatalogManager);
        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);

        foreach ([$catalogManager, $owner, $profile->user] as $user) {
            $this->actingAs($user)
                ->put(route('platform.developers.permissions.update', $profile), ['permissions' => self::ALL])
                ->assertForbidden();
        }

        $this->assertSame(['create_blocks'], $this->storedKeys($profile));

        auth()->logout();
        $this->put(route('platform.developers.permissions.update', $profile), ['permissions' => self::ALL])->assertRedirect(route('login'));
        $this->assertSame(['create_blocks'], $this->storedKeys($profile));
    }

    public function test_suspension_keeps_grants_and_reactivation_restores_them(): void
    {
        $authorization = app(DeveloperAuthorization::class);
        $profile = DeveloperProfile::factory()->withPermissions(DeveloperPermission::CreateBlocks, DeveloperPermission::CreateTemplates)->create();

        $this->actingAs($this->superAdmin)->post(route('platform.developers.suspend', $profile));
        $this->assertSame(['create_blocks', 'create_templates'], $this->storedKeys($profile));
        $this->assertFalse($authorization->allowsUser($profile->user, DeveloperPermission::CreateBlocks));

        // Grants of a suspended profile stay editable and take effect on reactivation.
        $this->updatePermissions($profile, ['create_templates'])->assertSessionHasNoErrors();
        $this->actingAs($this->superAdmin)->post(route('platform.developers.reactivate', $profile));

        $this->assertFalse($authorization->allowsUser($profile->user, DeveloperPermission::CreateBlocks));
        $this->assertTrue($authorization->allowsUser($profile->user, DeveloperPermission::CreateTemplates));
    }

    public function test_dashboard_reflects_effective_capabilities_on_every_request(): void
    {
        $profile = DeveloperProfile::factory()->withPermissions()->create();
        $developer = $profile->user;

        $this->assertCapabilities($developer, array_fill_keys(self::ALL, true));

        $this->updatePermissions($profile, ['create_templates', 'submit_marketplace_item']);
        $this->assertCapabilities($developer, ['create_blocks' => false, 'create_templates' => true, 'submit_marketplace_item' => true]);

        $this->updatePermissions($profile, self::ALL);
        $this->assertCapabilities($developer, array_fill_keys(self::ALL, true));

        $this->updatePermissions($profile, []);
        $this->assertCapabilities($developer, array_fill_keys(self::ALL, false));

        $this->updatePermissions($profile, self::ALL);
        $this->actingAs($this->superAdmin)->post(route('platform.developers.suspend', $profile));
        $this->actingAs($developer)->get(route('developer.dashboard'))->assertForbidden();

        // The Developer Platform exposes no authoring routes yet.
        $developerRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'developer' || str_starts_with($route->uri(), 'developer/'))
            ->map(fn ($route): string => $route->uri())
            ->values()
            ->all();
        $this->assertSame(['developer'], $developerRoutes);
    }

    public function test_developer_permissions_grant_no_workspace_site_or_platform_access(): void
    {
        $profile = DeveloperProfile::factory()->withPermissions()->create();
        $developer = $profile->user;
        Workspace::factory()->create()->addMember($developer, WorkspaceRole::ContentEditor);
        $site = Site::factory()->for(Workspace::factory()->create())->create();

        $this->assertSame(1, $developer->memberships()->count());
        foreach ([WorkspacePermission::ViewWorkspace, WorkspacePermission::PublishSite, WorkspacePermission::ManageMembers] as $permission) {
            $this->assertFalse(Gate::forUser($developer)->allows($permission->value), $permission->value);
        }
        foreach (PlatformPermission::cases() as $permission) {
            $this->assertFalse(Gate::forUser($developer)->allows($permission->value), $permission->value);
        }

        $this->actingAs($developer)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->get(route('sites.show', $site))
            ->assertNotFound();
        $this->actingAs($developer)->get(route('platform.developers.index'))->assertForbidden();
        $this->assertSame(1, $developer->memberships()->count());
    }

    public function test_platform_content_permission_belongs_to_super_admin_only(): void
    {
        $platform = app(PlatformAuthorization::class);
        $catalogManager = $this->userWithRole(PlatformRole::CatalogManager);
        $normal = User::factory()->create();
        $developer = DeveloperProfile::factory()->withPermissions()->create()->user;
        $permission = PlatformPermission::ManagePlatformContent->value;

        $this->assertSame('manage_platform_content', $permission);
        $this->assertContains(PlatformPermission::ManagePlatformContent, $platform->permissionsForRole(PlatformRole::SuperAdmin));
        $this->assertNotContains(PlatformPermission::ManagePlatformContent, $platform->permissionsForRole(PlatformRole::CatalogManager));

        $this->assertTrue(Gate::forUser($this->superAdmin)->allows($permission));
        foreach ([$catalogManager, $normal, $developer] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows($permission));
        }

        $this->actingAs($this->superAdmin)->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('platform.permissions', fn ($keys): bool => collect($keys)->contains($permission)));
    }

    public function test_platform_content_permission_grants_no_developer_or_workspace_access(): void
    {
        $authorization = app(DeveloperAuthorization::class);
        Workspace::factory()->create()->addMember($this->superAdmin, WorkspaceRole::Owner);
        $site = Site::factory()->for(Workspace::factory()->create())->create();

        $this->assertNull($this->superAdmin->developerProfile);
        foreach (DeveloperPermission::cases() as $permission) {
            $this->assertFalse($authorization->allowsUser($this->superAdmin, $permission));
        }
        $this->actingAs($this->superAdmin)->get(route('developer.dashboard'))->assertForbidden();
        $this->actingAs($this->superAdmin)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->get(route('sites.show', $site))
            ->assertNotFound();

        $this->assertSame(0, DeveloperProfile::query()->count());
        $this->assertSame(1, $this->superAdmin->memberships()->count());
    }

    /**
     * @param  array<string, bool>  $expected
     */
    private function assertCapabilities(User $developer, array $expected): void
    {
        $this->actingAs($developer)->get(route('developer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('developer/dashboard')
                ->where('capabilities', $expected)
                ->missing('profile.id')
                ->missing('permissions'));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function updatePermissions(DeveloperProfile $profile, array $permissions): TestResponse
    {
        return $this->actingAs($this->superAdmin)
            ->put(route('platform.developers.permissions.update', $profile), ['permissions' => $permissions]);
    }

    /**
     * @return list<string>
     */
    private function storedKeys(DeveloperProfile $profile): array
    {
        /** @var list<string> $keys */
        $keys = DB::table('developer_profile_permissions')
            ->where('developer_profile_id', $profile->id)
            ->pluck('permission')
            ->sort()
            ->values()
            ->all();

        return $keys;
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }
}
