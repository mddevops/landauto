<?php

namespace Database\Seeders;

use App\Models\User;
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
        $this->createUser('Александра Константиновна Преображенская', 'member@landflow.test');

        // Separate user for login/logout flows so they do not share the login rate limit
        // with the authenticated storage state.
        $this->createUser('Иван Петров', 'login@landflow.test');

        // Reused by verification-flow tests, including Playwright retries.
        $this->createUser('Мария Неподтверждённая', 'unverified@landflow.test', false);
    }

    private function createUser(string $name, string $email, bool $verified = true): void
    {
        (new User)->forceFill([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => $verified ? now() : null,
            'password' => 'e2e-password',
        ])->save();
    }
}
