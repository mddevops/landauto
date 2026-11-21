<?php

namespace Tests\Feature\Platform;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultWorkspacePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_registered_users_without_secrets(): void
    {
        $admin = User::factory()->create([
            'name' => 'Супер Админ',
            'email' => 'admin@landflow.test',
        ]);
        PlatformRoleAssignment::query()->create([
            'user_id' => $admin->id,
            'role' => PlatformRole::SuperAdmin->value,
        ]);

        $dealer = User::factory()->unverified()->create([
            'name' => 'Дилер',
            'email' => 'dealer@example.ru',
            'created_at' => '2026-11-01 10:00:00',
        ]);
        $workspace = Workspace::factory()->create();
        $workspace->addMember($dealer, WorkspaceRole::Owner);
        $workspace->plan()->associate(app(DefaultWorkspacePlan::class)->resolve())->save();

        $this->actingAs($admin)
            ->get(route('platform.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/users/index')
                ->where('total', 2)
                ->where('users', fn ($users): bool => collect($users)->pluck('email')->sort()->values()->all() === [
                    'admin@landflow.test',
                    'dealer@example.ru',
                ])
                ->where('users', fn ($users): bool => collect($users)->firstWhere('email', 'dealer@example.ru') === [
                    'name' => 'Дилер',
                    'email' => 'dealer@example.ru',
                    'registered_at' => $dealer->fresh()->created_at?->toIso8601String(),
                    'roles' => 'Владелец',
                    'plans' => 'Бесплатный',
                    'verified' => false,
                    'verified_label' => 'Нет',
                ] && collect($users)->firstWhere('email', 'admin@landflow.test')['roles'] === 'Суперадминистратор')
                ->missing('users.0.id')
                ->missing('users.0.password'));
    }

    public function test_catalog_manager_and_guests_cannot_open_the_directory(): void
    {
        $manager = User::factory()->create();
        PlatformRoleAssignment::query()->create([
            'user_id' => $manager->id,
            'role' => PlatformRole::CatalogManager->value,
        ]);

        $this->actingAs($manager)->get(route('platform.users.index'))->assertForbidden();

        auth()->logout();
        $this->get(route('platform.users.index'))->assertRedirect(route('login'));
    }
}
