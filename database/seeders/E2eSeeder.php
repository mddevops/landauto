<?php

namespace Database\Seeders;

use App\Enums\Entitlement;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Deterministic data for Playwright browser tests (tests/browser).
 *
 * Credentials are test-only and duplicated in tests/browser/support/users.ts.
 */
class E2eSeeder extends Seeder
{
    public function run(): void
    {
        // Known credentials must never be created in a development or production database.
        if (! app()->environment('e2e')) {
            throw new RuntimeException('E2eSeeder may only run in the e2e environment.');
        }

        // Long Russian name on purpose: layouts must survive realistic long user data.
        $member = $this->createUser('Александра Константиновна Преображенская', 'member@landflow.test');
        $this->createWorkspace($member, 'Личный автопарк');
        $workspaceWithSites = $this->createWorkspace($member, 'Автосалон Север');
        Site::factory()->for($workspaceWithSites)->create(['name' => 'Сайт автосалона']);
        Site::factory()->for($workspaceWithSites)->archived()->create(['name' => 'Архивный лендинг']);
        $this->createWorkspace($member, 'Недоступный Workspace', WorkspaceMemberStatus::Suspended);

        // Separate user for login/logout flows so they do not share the login rate limit
        // with the authenticated storage state.
        $loginUser = $this->createUser('Иван Петров', 'login@landflow.test');
        $this->createWorkspace($loginUser, 'Workspace Ивана');

        // Reused by verification-flow tests, including Playwright retries.
        $unverified = $this->createUser('Мария Неподтверждённая', 'unverified@landflow.test', false);
        $this->createWorkspace($unverified, 'Workspace Марии');

        // Core platform flow: creates Sites, so it is isolated from the `member` assertions.
        // The generous test-only limit keeps repeated runs against one server passing.
        $this->call([TemplateSeeder::class, OfficialBlockSeeder::class]);
        $plan = Plan::factory()->create(['key' => 'e2e-sites', 'name' => 'E2E Sites']);
        $plan->setEntitlement(Entitlement::MaxSites, 100);
        $creator = $this->createUser('Олег Создатель', 'creator@landflow.test');
        $this->createWorkspace($creator, 'Автосалон Юг', plan: $plan);
        $this->createWorkspace($creator, 'Сервисный центр Юг', plan: $plan);

        // Designer flow: creates its own Site, so it never touches the `creator` assertions.
        $designer = $this->createUser('Дина Дизайнерова', 'designer@landflow.test');
        $this->createWorkspace($designer, 'Студия Дины', plan: $plan);
    }

    private function createUser(string $name, string $email, bool $verified = true): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $verified ? now() : null,
            'password' => 'e2e-password',
        ])->save();

        return $user;
    }

    private function createWorkspace(
        User $user,
        string $name,
        WorkspaceMemberStatus $status = WorkspaceMemberStatus::Active,
        ?Plan $plan = null,
    ): Workspace {
        $workspace = Workspace::create(['name' => $name]);
        $workspace->forceFill(['plan_id' => $plan?->id])->save();
        $workspace->addMember($user, WorkspaceRole::Owner, $status);

        return $workspace;
    }
}
