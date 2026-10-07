<?php

namespace Tests\Feature;

use App\Blocks\BlockAuthoringAuthorization;
use App\Blocks\BlockCatalogAccess;
use App\Blocks\BlockSourceChecker;
use App\Enums\BlockRuntime;
use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Enums\PlatformPermission;
use App\Enums\WorkspaceRole;
use App\Models\BlockVersion;
use App\Models\Catalog\AutoSeries;
use App\Models\PlatformRoleAssignment;
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
            ['catalog-author@landflow.test', 'catalog@landflow.test', 'creator@landflow.test', 'dealer@landflow.test', 'designer@landflow.test', 'developer@landflow.test', 'domains-designer@landflow.test', 'domains@landflow.test', 'formats@landflow.test', 'integrations-admin@landflow.test', 'integrations-designer@landflow.test', 'integrations@landflow.test', 'interactive@landflow.test', 'licensee@landflow.test', 'licenses-admin@landflow.test', 'lifecycle-admin@landflow.test', 'lifecycle-designer@landflow.test', 'lifecycle@landflow.test', 'login@landflow.test', 'member@landflow.test', 'navigator@landflow.test', 'publisher@landflow.test', 'sandbox@landflow.test', 'studio-developer@landflow.test', 'team-designer@landflow.test', 'team-foreign@landflow.test', 'team-integrations@landflow.test', 'team-leads@landflow.test', 'team-owner@landflow.test', 'team-pricing@landflow.test', 'team-publisher@landflow.test'],
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

        $teamSite = Site::query()->where('subdomain', 'team-a-e2e')->sole();
        $this->assertTrue(app(PublishValidator::class)->validate($teamSite)->passes());
        $this->assertSame(10, $entitlements->limit($teamSite->workspace, Entitlement::MaxMembers));
        $this->assertSame(5, $teamSite->workspace->members()->count());
        $this->assertFalse($teamSite->workspace->members()->whereRelation('user', 'email', 'team-designer@landflow.test')->exists());

        $developer = User::query()->where('email', 'developer@landflow.test')->sole();
        $blockAuthoring = app(BlockAuthoringAuthorization::class);
        $this->assertNotNull($blockAuthoring->developerAuthor($developer));
        $this->assertFalse($blockAuthoring->canAuthorPlatformBlocks($developer));
        $this->assertFalse(PlatformRoleAssignment::query()->where('user_id', $developer->id)->exists());
        $this->assertTrue($blockAuthoring->canAuthorPlatformBlocks($catalogAdmin));

        $promo = BlockVersion::query()->whereRelation('definition', 'slug', 'e2e-studio-promo')->sole();
        $this->assertSame(BlockRuntime::Sandboxed, $promo->runtime);
        $this->assertTrue($promo->definition->isPlatformOwned());
        $this->assertSame([], app(BlockSourceChecker::class)->checkVersion($promo));
        $this->assertTrue(Site::query()->where('subdomain', 'sandbox-e2e')->sole()->popups()->exists());

        $showcase = BlockVersion::query()->whereRelation('definition', 'slug', 'e2e-partner-showcase')->sole();
        $this->assertTrue($showcase->definition->isDeveloperOwned());
        $this->assertSame(CatalogAccessMode::AdminGrant, $showcase->definition->access_mode);
        $this->assertSame([], app(BlockSourceChecker::class)->checkVersion($showcase));
        $licenseSite = Site::query()->where('subdomain', 'license-e2e')->sole();
        $this->assertSame(0, $licenseSite->licenses()->count());
        $this->assertNotNull(app(BlockCatalogAccess::class)->denial($licenseSite, $showcase->definition));
        $this->assertTrue(Gate::forUser(User::query()->where('email', 'licenses-admin@landflow.test')->sole())->allows(PlatformPermission::ManageSiteLicenses->value));
    }
}
