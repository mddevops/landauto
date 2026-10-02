<?php

namespace Tests\Feature;

use App\Enums\Entitlement;
use App\Models\Template;
use App\Models\User;
use App\Support\WorkspaceEntitlements;
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
            ['creator@landflow.test', 'login@landflow.test', 'member@landflow.test'],
            User::query()->whereNotNull('email_verified_at')->orderBy('email')->pluck('email')->all(),
        );

        $creatorWorkspaces = User::query()->where('email', 'creator@landflow.test')->sole()
            ->workspaces()->orderBy('name')->get();
        $entitlements = app(WorkspaceEntitlements::class);

        $this->assertCount(2, $creatorWorkspaces);
        foreach ($creatorWorkspaces as $workspace) {
            $this->assertSame(100, $entitlements->limit($workspace, Entitlement::MaxSites));
        }
        $this->assertTrue(Template::query()->where('slug', 'blank')->where('is_official', true)->exists());
    }
}
