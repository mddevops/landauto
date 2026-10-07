<?php

namespace Tests\Feature\Developers;

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
use App\Models\WorkspaceMember;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Mockery;
use Tests\TestCase;

class DeveloperProfilesTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);
    }

    public function test_only_super_admin_manages_developers(): void
    {
        $profile = DeveloperProfile::factory()->create(['display_name' => 'Студия Блоков', 'slug' => 'studio-blocks']);
        $catalogManager = $this->userWithRole(PlatformRole::CatalogManager);
        $owner = User::factory()->create();
        Workspace::factory()->create()->addMember($owner, WorkspaceRole::Owner);

        $this->assertTrue(Gate::forUser($this->superAdmin)->allows(PlatformPermission::ManageDevelopers->value));
        $this->assertFalse(Gate::forUser($catalogManager)->allows(PlatformPermission::ManageDevelopers->value));

        $this->actingAs($this->superAdmin)->get(route('platform.developers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/developers/index')
                ->has('developers', 1)
                ->where('developers.0.public_id', $profile->public_id)
                ->where('developers.0.display_name', 'Студия Блоков')
                ->where('developers.0.slug', 'studio-blocks')
                ->where('developers.0.email', $profile->user->email)
                ->where('developers.0.status_label', 'Активен')
                ->missing('developers.0.id')
                ->missing('developers.0.user_id'));

        foreach ([$catalogManager, $owner] as $user) {
            $this->actingAs($user)->get(route('platform.developers.index'))->assertForbidden();
            $this->actingAs($user)->post(route('platform.developers.store'), $this->grantData(User::factory()->create()))->assertForbidden();
            $this->actingAs($user)->post(route('platform.developers.suspend', $profile))->assertForbidden();
        }

        $this->assertSame(1, DeveloperProfile::query()->count());
        $this->assertSame(DeveloperProfileStatus::Active, $profile->fresh()?->status);

        auth()->logout();
        $this->get(route('platform.developers.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_grants_a_profile_to_an_existing_verified_user_only(): void
    {
        Log::spy();
        $target = User::factory()->create(['email' => 'dev@example.test']);
        $workspace = Workspace::factory()->create();
        $workspace->addMember($target, WorkspaceRole::Designer);
        $users = User::query()->count();
        $memberships = WorkspaceMember::query()->count();

        $this->actingAs($this->superAdmin)
            ->post(route('platform.developers.store'), [
                'email' => '  Dev@Example.TEST ',
                'display_name' => ' Студия Блоков ',
                'slug' => 'studio-blocks',
                'bio' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('platform.developers.index'))
            ->assertInertiaFlash('toast.type', 'success');

        $profile = DeveloperProfile::query()->sole();
        $this->assertSame($target->id, $profile->user_id);
        $this->assertTrue(Str::isUlid($profile->public_id));
        $this->assertSame(['Студия Блоков', 'studio-blocks', DeveloperProfileStatus::Active, null], [$profile->display_name, $profile->slug, $profile->status, $profile->bio]);
        $this->assertSame($profile->id, $target->developerProfile?->id);

        // Nothing else is created or granted.
        $this->assertSame($users, User::query()->count());
        $this->assertSame($memberships, WorkspaceMember::query()->count());
        $this->assertSame(0, PlatformRoleAssignment::query()->where('user_id', $target->id)->count());
        foreach (PlatformPermission::cases() as $permission) {
            $this->assertFalse(Gate::forUser($target)->allows($permission->value), $permission->value);
        }

        Log::shouldHaveReceived('info')->with('developer.profile_created', Mockery::on(
            fn (array $context): bool => $context === ['developer_profile' => $profile->public_id, 'actor_user_id' => $this->superAdmin->id],
        ));
    }

    public function test_grant_rejects_unknown_unverified_and_duplicate_users_and_invalid_slugs(): void
    {
        $unverified = User::factory()->unverified()->create(['email' => 'new@example.test']);
        $existing = DeveloperProfile::factory()->create(['slug' => 'taken-slug']);

        $cases = [
            'unknown' => [['email' => 'missing@example.test'], 'email', 'Пользователь с таким email не найден. Профиль выдаётся только существующей учётной записи.'],
            'unverified' => [['email' => $unverified->email], 'email', 'Пользователь ещё не подтвердил email.'],
            'duplicate user' => [['email' => $existing->user->email, 'slug' => 'other-slug'], 'email', 'У этого пользователя уже есть профиль разработчика.'],
            'duplicate slug' => [['slug' => 'taken-slug'], 'slug', 'Этот slug уже занят другим разработчиком.'],
        ];

        foreach ($cases as $name => [$override, $field, $message]) {
            $this->actingAs($this->superAdmin)
                ->post(route('platform.developers.store'), [...$this->grantData(User::factory()->create()), ...$override])
                ->assertSessionHasErrors([$field => $message]);
            $this->assertSame(1, DeveloperProfile::query()->count(), $name);
        }

        foreach (['Studio', '-studio', 'studio-', 'stu--dio', 'ab', 'студия', 'stu dio', str_repeat('a', 61)] as $slug) {
            $this->actingAs($this->superAdmin)
                ->post(route('platform.developers.store'), [...$this->grantData(User::factory()->create()), 'slug' => $slug])
                ->assertSessionHasErrors('slug');
        }

        $this->assertSame(1, DeveloperProfile::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'missing@example.test']);
    }

    public function test_profile_ownership_and_public_id_are_immutable_and_there_is_no_workspace_relation(): void
    {
        $profile = DeveloperProfile::factory()->create();

        foreach (['workspace_id', 'plan_id', 'subscription_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('developer_profiles', $column), $column);
        }

        $profile->user_id = User::factory()->create()->id;
        try {
            $profile->save();
            $this->fail('Ownership must not change.');
        } catch (LogicException) {
            $this->assertNotSame($profile->user_id, $profile->fresh()?->user_id);
        }

        $profile = $profile->fresh() ?? $profile;
        $this->expectException(LogicException::class);
        $profile->forceFill(['public_id' => (string) Str::ulid()])->save();
    }

    public function test_super_admin_suspends_and_reactivates_and_profiles_cannot_be_deleted(): void
    {
        Log::spy();
        $profile = DeveloperProfile::factory()->create(['display_name' => 'Студия']);

        $this->actingAs($this->superAdmin)->post(route('platform.developers.suspend', $profile))
            ->assertRedirect(route('platform.developers.index'))
            ->assertInertiaFlash('toast.message', 'Профиль «Студия» приостановлен.');
        $this->assertSame(DeveloperProfileStatus::Suspended, $profile->fresh()?->status);

        $this->actingAs($this->superAdmin)->get(route('platform.developers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('developers.0.status_label', 'Приостановлен'));

        $this->actingAs($this->superAdmin)->post(route('platform.developers.reactivate', $profile))
            ->assertInertiaFlash('toast.message', 'Профиль «Студия» восстановлен.');
        $this->assertSame(DeveloperProfileStatus::Active, $profile->fresh()?->status);

        Log::shouldHaveReceived('info')->with('developer.profile_suspended', Mockery::any())->once();
        Log::shouldHaveReceived('info')->with('developer.profile_reactivated', Mockery::any())->once();

        $this->actingAs($this->superAdmin)->post(route('platform.developers.suspend', (string) Str::ulid()))->assertNotFound();
        $this->actingAs($this->superAdmin)->delete('/platform/developers/'.$profile->public_id)->assertNotFound();
        $this->assertFalse(collect(Route::getRoutes()->getRoutes())->contains(
            fn ($route): bool => str_starts_with($route->uri(), 'platform/developers') && in_array('DELETE', $route->methods(), true),
        ));
        $this->assertSame(1, DeveloperProfile::query()->count());
    }

    public function test_developer_platform_requires_an_own_active_profile(): void
    {
        $profile = DeveloperProfile::factory()->create(['display_name' => 'Студия', 'slug' => 'studio', 'bio' => 'О студии']);
        $developer = $profile->user;
        $normal = User::factory()->create();

        $this->actingAs($developer)->get(route('developer.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('developer/dashboard')
                ->where('profile.public_id', $profile->public_id)
                ->where('profile.display_name', 'Студия')
                ->where('profile.slug', 'studio')
                ->where('profile.status_label', 'Активен')
                ->missing('profile.id')
                ->missing('profile.user_id')
                ->where('developer.active', true));

        $this->actingAs($normal)->get(route('developer.dashboard'))->assertForbidden();
        $this->actingAs($normal)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('developer.active', false));
        $this->actingAs($this->superAdmin)->get(route('developer.dashboard'))->assertForbidden();
        $this->assertSame(1, DeveloperProfile::query()->count(), 'no profile is created automatically');

        // A suspension applies to the very next request of an existing session.
        $profile->forceFill(['status' => DeveloperProfileStatus::Suspended])->save();
        $this->actingAs($developer)->get(route('developer.dashboard'))->assertForbidden();
        $this->actingAs($developer)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('developer.active', false));

        $unverified = DeveloperProfile::factory()->for(User::factory()->unverified())->create();
        $this->actingAs($unverified->user)->get(route('developer.dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_developer_profile_grants_no_workspace_or_platform_access(): void
    {
        $profile = DeveloperProfile::factory()->create();
        $developer = $profile->user;
        $workspaceA = Workspace::factory()->create();
        $workspaceA->addMember($developer, WorkspaceRole::ContentEditor);
        $siteA = Site::factory()->for($workspaceA)->create();
        $siteB = Site::factory()->for(Workspace::factory()->create())->create();
        $session = [WorkspaceContext::SESSION_KEY => $workspaceA->public_id];

        $this->actingAs($developer)->withSession($session)->get(route('sites.show', $siteA))->assertOk();
        $this->actingAs($developer)->withSession($session)->get(route('sites.show', $siteB))->assertNotFound();
        $this->actingAs($developer)->withSession([WorkspaceContext::SESSION_KEY => $siteB->workspace->public_id])->get(route('sites.show', $siteB))->assertNotFound();

        $this->actingAs($developer)->withSession($session)->get(route('dashboard'));
        foreach ([WorkspacePermission::PublishSite, WorkspacePermission::ManageMembers, WorkspacePermission::EditPrices] as $permission) {
            $this->assertFalse(Gate::forUser($developer)->allows($permission->value), $permission->value);
        }
        foreach (PlatformPermission::cases() as $permission) {
            $this->assertFalse(Gate::forUser($developer)->allows($permission->value), $permission->value);
        }
        $this->assertSame(1, $developer->memberships()->count());

        // Suspending the profile leaves Workspace access untouched.
        $this->actingAs($this->superAdmin)->post(route('platform.developers.suspend', $profile));
        $this->actingAs($developer)->withSession($session)->get(route('sites.show', $siteA))->assertOk();
        $this->actingAs($developer)->withSession($session)->get(route('developer.dashboard'))->assertForbidden();
        $this->assertSame(1, $developer->memberships()->count());
    }

    public function test_platform_roles_and_developer_profiles_are_independent(): void
    {
        $user = User::factory()->create(['email' => 'catalog@example.test']);

        $this->artisan('platform:role', ['action' => 'grant', 'email' => 'catalog@example.test', 'role' => 'catalog_manager'])->assertSuccessful();
        $this->assertSame(0, DeveloperProfile::query()->count());
        $this->assertSame(0, DeveloperProfile::query()->where('user_id', $this->superAdmin->id)->count());

        $this->actingAs($this->superAdmin)->post(route('platform.developers.store'), $this->grantData($user))->assertSessionHasNoErrors();
        $this->assertSame([PlatformRole::CatalogManager], $user->fresh()?->platformRoleAssignments->map->role->all());
        $this->assertFalse(Gate::forUser($user->fresh())->allows(PlatformPermission::ManageDevelopers->value));

        // A Super Admin may hold a profile too, but only when granted explicitly.
        $this->actingAs($this->superAdmin)->post(route('platform.developers.store'), [...$this->grantData($this->superAdmin), 'slug' => 'landflow-admin'])->assertSessionHasNoErrors();
        $this->actingAs($this->superAdmin)->get(route('developer.dashboard'))->assertOk();
        $this->assertSame(1, PlatformRoleAssignment::query()->where('user_id', $this->superAdmin->id)->count());
    }

    public function test_account_with_a_developer_profile_cannot_be_deleted(): void
    {
        $profile = DeveloperProfile::factory()->create();

        $this->actingAs($profile->user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors(['account' => 'Учётную запись с профилем разработчика нельзя удалить самостоятельно. Обратитесь в поддержку Landflow.'])
            ->assertRedirect(route('profile.edit'));

        $this->assertAuthenticatedAs($profile->user);
        $this->assertDatabaseHas('users', ['id' => $profile->user_id]);
        $this->assertDatabaseHas('developer_profiles', ['id' => $profile->id]);
    }

    /**
     * @return array<string, string>
     */
    private function grantData(User $user): array
    {
        return [
            'email' => $user->email,
            'display_name' => 'Студия '.$user->id,
            'slug' => 'studio-'.$user->id,
        ];
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh() ?? $user;
    }
}
