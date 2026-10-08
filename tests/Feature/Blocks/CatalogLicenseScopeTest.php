<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockCatalogAccess;
use App\Enums\CatalogAccessMode;
use App\Enums\CatalogLicenseScope;
use App\Enums\CatalogLicenseSource;
use App\Enums\Entitlement;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\CatalogLicense;
use App\Models\DeveloperProfile;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlatformRoleAssignment;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

/**
 * D-121: a Site license covers one Site; a Workspace license covers every current and future Site
 * of that Workspace and nothing outside it. No account-wide license exists.
 */
class CatalogLicenseScopeTest extends TestCase
{
    use RefreshDatabase;

    private const DENIED = 'Блок выдаёт администратор Landflow для сайта или всего пространства.';

    private User $owner;

    private Workspace $workspace;

    private Workspace $otherWorkspace;

    private Site $siteA;

    private Site $siteB;

    private Site $siteC;

    private BlockDefinition $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, 10);

        // One user who belongs to both Workspaces: a license never follows the user.
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['plan_id' => $plan->id, 'name' => 'Автосалон Север']);
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->otherWorkspace = Workspace::factory()->create(['plan_id' => $plan->id, 'name' => 'Автосалон Юг']);
        $this->otherWorkspace->addMember($this->owner, WorkspaceRole::Owner);

        $this->siteA = $this->siteIn($this->workspace, 'Сайт А', 'site-a');
        $this->siteB = $this->siteIn($this->workspace, 'Сайт Б', 'site-b');
        $this->siteC = $this->siteIn($this->otherWorkspace, 'Сайт В', 'site-c');

        $profile = DeveloperProfile::factory()->withPermissions()->create();
        $this->block = BlockDefinition::factory()->developer($profile)->create([
            'slug' => 'dev-grant',
            'name' => 'Карточка по выдаче',
            'access_mode' => CatalogAccessMode::AdminGrant,
        ]);
        BlockVersion::factory()->sandboxed('<p>{{ title }}</p>')->for($this->block, 'definition')->create([
            'schema_json' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Текст', 'default' => 'Привет']]],
        ]);
    }

    public function test_site_license_covers_only_that_site(): void
    {
        CatalogLicense::factory()->forSite($this->siteA)->ofBlock($this->block)->create();

        $this->add($this->siteA)->assertSessionHasNoErrors();
        $this->add($this->siteB)->assertSessionHasErrors(['block' => self::DENIED]);
        $this->add($this->siteC)->assertSessionHasErrors(['block' => self::DENIED]);
        $this->assertTrue($this->libraryAllows($this->siteA));
        $this->assertFalse($this->libraryAllows($this->siteB));
        $this->assertFalse($this->libraryAllows($this->siteC));
    }

    public function test_workspace_license_covers_current_and_future_sites_of_that_workspace_only(): void
    {
        CatalogLicense::factory()->forWorkspace($this->workspace)->ofBlock($this->block)->create();

        $this->add($this->siteA)->assertSessionHasNoErrors();
        $this->add($this->siteB)->assertSessionHasNoErrors();

        // A Site created after the grant is covered without copying license rows.
        $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id])
            ->post(route('sites.store'), ['name' => 'Новый сайт', 'site_type' => 'landing', 'start' => 'blank'])
            ->assertSessionHasNoErrors();
        $newSite = Site::query()->where('name', 'Новый сайт')->sole();
        $this->assertSame($this->workspace->id, $newSite->workspace_id);
        $this->add($newSite)->assertSessionHasNoErrors();
        $this->assertTrue($this->libraryAllows($newSite));
        $this->assertSame(1, CatalogLicense::query()->count());

        // The same user's other Workspace gains nothing.
        $this->add($this->siteC)->assertSessionHasErrors(['block' => self::DENIED]);
        $this->assertFalse($this->libraryAllows($this->siteC));
    }

    public function test_effective_licenses_combine_site_and_workspace_scope(): void
    {
        $siteLicense = CatalogLicense::factory()->forSite($this->siteA)->ofBlock($this->block)->create();
        $workspaceLicense = CatalogLicense::factory()->forWorkspace($this->workspace)->ofBlock($this->block)->create();
        CatalogLicense::factory()->forWorkspace($this->otherWorkspace)->ofBlock(
            BlockDefinition::query()->where('slug', 'hero')->sole(),
        )->create();

        $this->assertEqualsCanonicalizing(
            [$siteLicense->id, $workspaceLicense->id],
            CatalogLicense::query()->effectiveFor($this->siteA)->pluck('id')->all(),
        );
        $this->assertSame([$workspaceLicense->id], CatalogLicense::query()->effectiveFor($this->siteB)->pluck('id')->all());

        $access = app(BlockCatalogAccess::class);
        $this->assertNull($access->workspaceDenial($this->workspace, $this->block));
        $this->assertNotNull($access->workspaceDenial($this->otherWorkspace, $this->block));

        // Revoking the Workspace license leaves the Site license in force.
        $workspaceLicense->delete();
        $this->assertNull($access->denial($this->siteA, $this->block));
        $this->assertNotNull($access->denial($this->siteB, $this->block));
    }

    public function test_license_needs_exactly_one_item_and_one_target_matching_its_scope(): void
    {
        $template = Template::factory()->create();
        $invalid = [
            ['scope' => CatalogLicenseScope::Site, 'site_id' => $this->siteA->id],
            ['scope' => CatalogLicenseScope::Site, 'site_id' => $this->siteA->id, 'block_definition_id' => $this->block->id, 'template_id' => $template->id],
            ['scope' => CatalogLicenseScope::Site, 'block_definition_id' => $this->block->id],
            ['scope' => CatalogLicenseScope::Site, 'site_id' => $this->siteA->id, 'workspace_id' => $this->workspace->id, 'block_definition_id' => $this->block->id],
            ['scope' => CatalogLicenseScope::Site, 'workspace_id' => $this->workspace->id, 'block_definition_id' => $this->block->id],
            ['scope' => CatalogLicenseScope::Workspace, 'block_definition_id' => $this->block->id],
            ['scope' => CatalogLicenseScope::Workspace, 'site_id' => $this->siteA->id, 'block_definition_id' => $this->block->id],
            ['scope' => CatalogLicenseScope::Workspace, 'workspace_id' => $this->workspace->id, 'site_id' => $this->siteA->id, 'template_id' => $template->id],
        ];

        foreach ($invalid as $attributes) {
            try {
                (new CatalogLicense)->forceFill([...$attributes, 'source' => CatalogLicenseSource::AdminGrant])->save();
                $this->fail('inconsistent license saved: '.json_encode(array_keys($attributes)));
            } catch (LogicException) {
                $this->assertSame(0, CatalogLicense::query()->count());
            }
        }

        $license = CatalogLicense::factory()->forWorkspace($this->workspace)->ofTemplate($template)->create();
        $this->assertSame([CatalogLicenseScope::Workspace, null, $this->workspace->id], [$license->scope, $license->site_id, $license->workspace_id]);

        $this->expectException(LogicException::class);
        $license->forceFill(['scope' => CatalogLicenseScope::Site, 'site_id' => $this->siteA->id, 'workspace_id' => null])->save();
    }

    public function test_each_item_and_scope_pair_is_unique(): void
    {
        $template = Template::factory()->create();
        $pairs = [
            fn () => CatalogLicense::factory()->forSite($this->siteA)->ofBlock($this->block),
            fn () => CatalogLicense::factory()->forSite($this->siteA)->ofTemplate($template),
            fn () => CatalogLicense::factory()->forWorkspace($this->workspace)->ofBlock($this->block),
            fn () => CatalogLicense::factory()->forWorkspace($this->workspace)->ofTemplate($template),
        ];

        foreach ($pairs as $pair) {
            $pair()->create();
        }
        $this->assertSame(4, CatalogLicense::query()->count());

        foreach ($pairs as $pair) {
            try {
                $pair()->create();
                $this->fail('duplicate license saved');
            } catch (QueryException) {
                $this->assertSame(4, CatalogLicense::query()->count());
            }
        }
    }

    public function test_admin_page_shows_both_scopes_without_internal_ids(): void
    {
        $template = Template::factory()->create(['name' => 'Шаблон Север']);
        CatalogLicense::factory()->forSite($this->siteA)->ofBlock($this->block)->create();
        CatalogLicense::factory()->forWorkspace($this->workspace)->ofTemplate($template)->create();
        $admin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $admin->id, 'role' => PlatformRole::SuperAdmin->value]);

        $this->actingAs($admin)->get(route('platform.licenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('licenses', 2)
                ->where('licenses', function ($licenses): bool {
                    $rows = collect($licenses)->keyBy('scope');
                    $this->assertSame(['Шаблон Север', 'template', 'Всё пространство', 'Автосалон Север', 'Автосалон Север'], [
                        $rows['workspace']['item'], $rows['workspace']['kind'], $rows['workspace']['scope_label'], $rows['workspace']['target'], $rows['workspace']['workspace'],
                    ]);
                    $this->assertSame(['Карточка по выдаче', 'block', 'Один сайт', 'Сайт А', 'site-a', 'Автосалон Север'], [
                        $rows['site']['item'], $rows['site']['kind'], $rows['site']['scope_label'], $rows['site']['target'], $rows['site']['subdomain'], $rows['site']['workspace'],
                    ]);

                    foreach ($rows as $row) {
                        foreach (['id', 'site_id', 'workspace_id', 'block_definition_id', 'template_id', 'granted_by_user_id'] as $internal) {
                            $this->assertArrayNotHasKey($internal, $row);
                        }
                    }

                    return true;
                }));
    }

    private function siteIn(Workspace $workspace, string $name, string $subdomain): Site
    {
        $site = Site::factory()->for($workspace)->create(['name' => $name, 'subdomain' => $subdomain]);
        Page::factory()->for($site)->home()->create();

        return $site;
    }

    private function add(Site $site): TestResponse
    {
        $home = $site->pages()->where('is_home', true)->sole();

        return $this->actingAs($this->owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->post(route('sites.blocks.store', [$site, $home]), ['block' => 'dev-grant']);
    }

    private function libraryAllows(Site $site): bool
    {
        $allowed = null;
        $this->actingAs($this->owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $site->workspace->public_id])
            ->get(route('sites.designer', $site))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$allowed): void {
                $page->where('library', function ($library) use (&$allowed): bool {
                    $allowed = collect($library)->firstWhere('slug', 'dev-grant')['available'];

                    return true;
                });
            });

        return $allowed === true;
    }
}
