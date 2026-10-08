<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockVersionGrants;
use App\Enums\CatalogAccessMode;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\CatalogLicense;
use App\Models\DeveloperProfile;
use App\Models\Page;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Publishing\PublishSite;
use App\Publishing\PublishValidator;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

/**
 * D-122: a Site keeps the exact Block Versions it installed lawfully (Site + BlockVersion grants),
 * while new versions, new installs and other Sites follow current catalog access.
 */
class BlockVersionGrandfatheringTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase;

    private const PAID = 'Платный блок: нужна лицензия на этот сайт или на всё пространство. Покупка в Landflow пока недоступна.';

    private BlockDefinition $block;

    private BlockVersion $v100;

    private Site $siteB;

    private Page $homeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $this->siteB = Site::factory()->for($this->workspace)->create(['name' => 'Сайт Б']);
        $this->homeB = Page::factory()->for($this->siteB)->home()->create();

        $profile = DeveloperProfile::factory()->withPermissions()->create();
        $this->block = BlockDefinition::factory()->developer($profile)->create(['slug' => 'dev-card', 'name' => 'Карточка студии']);
        $this->v100 = $this->publishVersion('1.0.0');
    }

    public function test_case_a_free_block_turned_paid_stays_usable_where_installed(): void
    {
        $this->add($this->site, $this->home)->assertSessionHasNoErrors();
        $installed = $this->devInstance();
        $this->assertTrue(app(BlockVersionGrants::class)->has($this->site, $this->v100));

        $this->makePaid();

        $this->as()->patch(route('sites.blocks.state', [$this->site, $installed]), ['state' => ['title' => 'Новый текст']])
            ->assertSessionHasNoErrors();
        $this->assertSame('Новый текст', $installed->fresh()?->state_json['title']);
        $this->as()->post(route('sites.blocks.duplicate', [$this->site, $installed]))->assertSessionHasNoErrors();
        $this->assertSame(2, $this->devCount($this->site));
        $this->assertNotContains('block_access_denied', $this->issueCodes($this->site));
        $this->publish();

        // The catalog Add path is a new acquisition and follows current access, even here.
        $this->add($this->site, $this->home)->assertSessionHasErrors(['block' => self::PAID]);
        $this->assertFalse($this->libraryAllows($this->site));
        $this->assertSame(2, $this->devCount($this->site));

        $this->add($this->siteB, $this->homeB)->assertSessionHasErrors(['block' => self::PAID]);
        $this->assertFalse($this->libraryAllows($this->siteB));
        $this->assertSame(0, $this->devCount($this->siteB));
    }

    public function test_case_b_new_version_needs_current_access_while_the_installed_one_keeps_working(): void
    {
        $this->add($this->site, $this->home)->assertSessionHasNoErrors();
        $installed = $this->devInstance();
        $this->makePaid();
        $v110 = $this->publishVersion('1.1.0');

        $this->add($this->site, $this->home)->assertSessionHasErrors(['block' => self::PAID]);
        $this->assertFalse($this->libraryAllows($this->site));
        $this->assertSame(1, $this->devCount($this->site));

        $this->as()->post(route('sites.blocks.duplicate', [$this->site, $installed]))->assertSessionHasNoErrors();
        $this->assertSame($this->v100->id, $this->devInstance()->block_version_id);
        $this->assertNotContains('block_access_denied', $this->issueCodes($this->site));
        $this->publish();

        // A legitimate acquisition of 1.1.0 records its own grant.
        $license = CatalogLicense::factory()->forSite($this->site)->ofBlock($this->block)->create();
        $this->add($this->site, $this->home)->assertSessionHasNoErrors();
        $this->assertSame($v110->id, $this->devInstance()->block_version_id);
        $license->delete();
        $this->assertTrue(app(BlockVersionGrants::class)->has($this->site, $v110));
        $this->assertNotContains('block_access_denied', $this->issueCodes($this->site));
    }

    public function test_case_c_revoked_license_keeps_installed_versions_and_blocks_new_installs(): void
    {
        $this->makePaid();
        $license = CatalogLicense::factory()->forWorkspace($this->workspace)->ofBlock($this->block)->create();
        $this->add($this->site, $this->home)->assertSessionHasNoErrors();
        $installed = $this->devInstance();
        $grants = DB::table('site_block_version_grants')->count();

        $license->delete();

        $this->assertSame($grants, DB::table('site_block_version_grants')->count());
        $this->assertNotNull($installed->fresh());
        $this->assertNotNull($this->v100->fresh());
        $this->as()->patch(route('sites.blocks.state', [$this->site, $installed]), ['state' => ['title' => 'После отзыва']])
            ->assertSessionHasNoErrors();
        $this->assertNotContains('block_access_denied', $this->issueCodes($this->site));
        $this->publish();

        // Site B never installed it, so the revocation shuts it out.
        $this->add($this->siteB, $this->homeB)->assertSessionHasErrors(['block' => self::PAID]);
        $this->assertContains('block_access_denied', $this->codesWithUngrantedPlacement());
    }

    public function test_publishing_never_creates_grants(): void
    {
        $this->makePaid();
        BlockInstance::factory()->create([
            'page_id' => $this->home->id,
            'block_version_id' => $this->v100->id,
            'sort_order' => 9,
            'state_json' => ['title' => 'Без гранта'],
        ]);
        $before = DB::table('site_block_version_grants')->count();

        $this->assertContains('block_access_denied', $this->issueCodes($this->site));
        $this->actAsMember($this->owner);
        $this->assertFalse(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());

        // Current access allows publishing, still without recording a grant.
        CatalogLicense::factory()->forSite($this->site)->ofBlock($this->block)->create();
        $this->publish();
        $this->assertSame($before, DB::table('site_block_version_grants')->count());
        $this->assertFalse(app(BlockVersionGrants::class)->has($this->site, $this->v100));
    }

    public function test_restore_brings_back_grants_without_rechecking_access(): void
    {
        $this->makePaid();
        $license = CatalogLicense::factory()->forSite($this->site)->ofBlock($this->block)->create();
        $this->add($this->site, $this->home)->assertSessionHasNoErrors();
        $version = $this->publish();

        $license->delete();
        $this->as()->delete(route('sites.blocks.destroy', [$this->site, $this->devInstance()]))->assertSessionHasNoErrors();
        DB::table('site_block_version_grants')->where('site_id', $this->site->id)->delete();
        $this->assertSame(0, $this->devCount($this->site));

        $this->as()->post(route('sites.versions.restore', ['site' => $this->site, 'version' => $version]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.publishing.show', $this->site));

        $this->assertSame(1, $this->devCount($this->site));
        $this->assertTrue(app(BlockVersionGrants::class)->has($this->site, $this->v100));
        $this->assertFalse(app(BlockVersionGrants::class)->has($this->siteB, $this->v100));
        $this->assertNotContains('block_access_denied', $this->issueCodes($this->site));
        $this->publish();
        $this->add($this->siteB, $this->homeB)->assertSessionHasErrors(['block' => self::PAID]);
    }

    private function publishVersion(string $version): BlockVersion
    {
        return BlockVersion::factory()->sandboxed('<p>{{ title }}</p>')->for($this->block, 'definition')->create([
            'version' => $version,
            'schema_json' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Текст', 'default' => 'Привет']]],
        ]);
    }

    private function makePaid(): void
    {
        $this->block->forceFill([
            'access_mode' => CatalogAccessMode::Paid,
            'site_price_minor' => 490_000,
            'workspace_price_minor' => 1_490_000,
            'price_currency' => 'RUB',
        ])->save();
    }

    private function add(Site $site, Page $home): TestResponse
    {
        return $this->as()->post(route('sites.blocks.store', [$site, $home]), ['block' => 'dev-card']);
    }

    private function devInstance(): BlockInstance
    {
        return BlockInstance::query()->whereHas('version', fn ($query) => $query->where('block_definition_id', $this->block->id))
            ->whereHas('page', fn ($query) => $query->where('site_id', $this->site->id))
            ->latest('id')
            ->firstOrFail();
    }

    private function devCount(Site $site): int
    {
        return BlockInstance::query()->whereHas('version', fn ($query) => $query->where('block_definition_id', $this->block->id))
            ->whereHas('page', fn ($query) => $query->where('site_id', $site->id))
            ->count();
    }

    /**
     * @return list<string>
     */
    private function issueCodes(Site $site): array
    {
        return app(PublishValidator::class)->validate(Site::query()->findOrFail($site->id))->errorCodes();
    }

    /**
     * @return list<string>
     */
    private function codesWithUngrantedPlacement(): array
    {
        BlockInstance::factory()->create([
            'page_id' => $this->homeB->id,
            'block_version_id' => $this->v100->id,
            'sort_order' => 0,
            'state_json' => ['title' => 'Привет'],
        ]);

        return $this->issueCodes($this->siteB);
    }

    private function publish(): PublishedVersion
    {
        $this->actAsMember($this->owner);
        $outcome = app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
        $this->assertTrue($outcome->succeeded(), implode(' ', $outcome->validation?->errorCodes() ?? []));

        return $outcome->version ?? throw new LogicException('No version.');
    }

    private function libraryAllows(Site $site): bool
    {
        $allowed = null;
        $this->as()->get(route('sites.designer', $site))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$allowed): void {
                $page->where('library', function ($library) use (&$allowed): bool {
                    $allowed = collect($library)->firstWhere('slug', 'dev-card')['available'];

                    return true;
                });
            });

        return $allowed === true;
    }

    private function as(): static
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
