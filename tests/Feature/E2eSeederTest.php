<?php

namespace Tests\Feature;

use App\Enums\Entitlement;
use App\Enums\PlatformPermission;
use App\Enums\WorkspaceRole;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\Template;
use App\Models\User;
use App\Publishing\PublishValidator;
use App\Support\WorkspaceEntitlements;
use Database\Seeders\E2eSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class E2eSeederTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

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
        Storage::fake('local');

        $this->seed(E2eSeeder::class);

        $this->assertSame(
            ['catalog@landflow.test', 'creator@landflow.test', 'dealer@landflow.test', 'designer@landflow.test', 'integrations-admin@landflow.test', 'integrations-designer@landflow.test', 'integrations@landflow.test', 'interactive@landflow.test', 'lifecycle-admin@landflow.test', 'lifecycle-designer@landflow.test', 'lifecycle@landflow.test', 'login@landflow.test', 'member@landflow.test', 'navigator@landflow.test', 'publisher@landflow.test'],
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

        $catalogAdmin = User::query()->where('email', 'catalog@landflow.test')->sole();
        $dealer = User::query()->where('email', 'dealer@landflow.test')->sole();
        $this->assertTrue(Gate::forUser($catalogAdmin)->allows(PlatformPermission::EditCatalog->value));
        $this->assertFalse(Gate::forUser($dealer)->allows(PlatformPermission::EditCatalog->value));
        $this->assertTrue(AutoSeries::query()->available()->where('url', 'sedan')->exists());

        $showcase = Site::query()->where('name', 'Витрина Запад')->sole();
        $this->assertSame(2, $showcase->vehicles()->count());
        $this->assertSame(1, SiteOffer::query()->whereRelation('vehicle', 'site_id', $showcase->id)->count());
        Storage::disk('local')->assertExists(['series-media/e2e/front_3_4.png', 'series-media/e2e/side.png']);

        $lifecycle = Site::query()->where('subdomain', 'lifecycle-e2e')->sole();
        $this->assertTrue(app(PublishValidator::class)->validate($lifecycle)->passes());
        $this->assertSame(WorkspaceRole::Designer, $lifecycle->workspace->members()->whereRelation('user', 'email', 'lifecycle-designer@landflow.test')->sole()->role);

        $integrations = Site::query()->where('subdomain', 'integrations-e2e')->sole();
        $this->assertTrue(app(PublishValidator::class)->validate($integrations)->passes());
        $this->assertSame(0, $integrations->integrationBindings()->count());
        $this->assertSame(WorkspaceRole::Admin, $integrations->workspace->members()->whereRelation('user', 'email', 'integrations-admin@landflow.test')->sole()->role);
    }
}
