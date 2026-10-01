<?php

namespace Database\Seeders;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
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
        $this->createWorkspace($member, 'Автосалон Север');
        $this->createWorkspace($member, 'Недоступный Workspace', WorkspaceMemberStatus::Suspended);

        // Separate user for login/logout flows so they do not share the login rate limit
        // with the authenticated storage state.
        $loginUser = $this->createUser('Иван Петров', 'login@landflow.test');
        $this->createWorkspace($loginUser, 'Workspace Ивана');

        // Reused by verification-flow tests, including Playwright retries.
        $unverified = $this->createUser('Мария Неподтверждённая', 'unverified@landflow.test', false);
        $this->createWorkspace($unverified, 'Workspace Марии');
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
    ): void {
        $workspace = Workspace::create(['name' => $name]);
        $workspace->addMember($user, WorkspaceRole::Owner, $status);
    }
}
