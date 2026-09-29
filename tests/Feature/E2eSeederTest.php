<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\E2eSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class E2eSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_e2e_seeder_refuses_to_run_outside_the_e2e_environment(): void
    {
        $this->expectException(RuntimeException::class);

        try {
            $this->seed(E2eSeeder::class);
        } finally {
            $this->assertSame(0, User::query()->count());
        }
    }

    public function test_e2e_seeder_creates_verified_test_users_in_the_e2e_environment(): void
    {
        $this->app['env'] = 'e2e';

        $this->seed(E2eSeeder::class);

        $this->assertSame(
            ['login@landflow.test', 'member@landflow.test'],
            User::query()->whereNotNull('email_verified_at')->orderBy('email')->pluck('email')->all(),
        );
    }
}
