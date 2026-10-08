<?php

namespace Tests\Feature\Templates;

use App\Blocks\BlockCatalogAccess;
use App\Blocks\BlockStateDefaults;
use App\Blocks\BlockVersionGrants;
use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Enums\PlatformRole;
use App\Enums\SiteType;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\CatalogLicense;
use App\Models\DeveloperProfile;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlatformRoleAssignment;
use App\Models\Site;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Models\User;
use App\Models\Workspace;
use App\Publishing\PublishValidator;
use App\Support\WorkspaceContext;
use App\Templates\TemplatePublisher;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * P9-015: a published Template Version is copied into independent Site Pages and Block
 * Instances; compatibility and catalog access (D-121, D-122) are checked by the backend.
 */
class TemplateInstallationTest extends TestCase
{
    use RefreshDatabase;

    private DeveloperProfile $profile;

    private User $customer;

    private Workspace $workspace;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->profile = DeveloperProfile::factory()->withPermissions()->create(['display_name' => 'Студия А']);
        $this->plan = Plan::factory()->create();
        $this->plan->setEntitlement(Entitlement::MaxSites, 5);
        $this->plan->setEntitlement(Entitlement::MultiPageSites, true);
        $this->plan->setEntitlement(Entitlement::RemoveBranding, false);
        $this->customer = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
        $this->workspace->addMember($this->customer, WorkspaceRole::Owner);
    }

    public function test_installation_copies_pages_and_blocks_with_new_ids_and_remapped_references(): void
    {
        $template = $this->template([SiteType::MultiPage]);
        $home = $template->pages()->sole();
        $about = $this->addPage($template, 'О компании', 'about');
        $hero = $this->placeBlock($home, 'hero');
        $cta = $this->placeBlock($home, 'cta');
        $header = $this->placeBlock($about, 'header');
        TemplateBlock::query()->whereKey($cta->id)->update(['is_hidden' => true]);
        $this->setState($hero, [
            ...$hero->state_json,
            'title' => 'Автомобили шаблона',
            'primary_button' => ['label' => 'О нас', 'action' => ['type' => 'open_page', 'page' => $about->public_id]],
            'secondary_button' => ['label' => 'Ниже', 'action' => ['type' => 'scroll_to', 'block' => $cta->public_id]],
        ]);
        $this->setState($header, [
            ...$header->state_json,
            'menu' => [['id' => (string) Str::ulid(), 'label' => 'Главная', 'action' => ['type' => 'open_page', 'page' => $home->public_id]]],
        ]);
        $this->publish($template);

        $this->createSite($template, SiteType::MultiPage)->assertSessionHasNoErrors();

        $site = Site::query()->sole();
        $this->assertSame(SiteType::MultiPage, $site->site_type);
        $pages = $site->pages()->orderBy('sort_order')->get();
        $this->assertSame([['Главная', 'home', true], ['О компании', 'about', false]], $pages->map(fn (Page $page): array => [$page->title, $page->slug, $page->is_home])->all());
        [$siteHome, $siteAbout] = $pages->all();
        $this->assertNotContains($siteHome->public_id, [$home->public_id, $about->public_id]);

        $homeBlocks = $siteHome->blocks()->orderBy('sort_order')->get();
        $this->assertSame(['hero', 'cta'], $homeBlocks->map(fn (BlockInstance $block): string => $block->version->definition->slug)->all());
        [$siteHero, $siteCta] = $homeBlocks->all();
        $this->assertSame($hero->block_version_id, $siteHero->block_version_id, 'the pinned Block Version is kept');
        $this->assertNotSame($hero->public_id, $siteHero->public_id);
        $this->assertTrue($siteCta->is_hidden);
        $this->assertSame('Автомобили шаблона', $siteHero->state_json['title']);
        $this->assertSame(['type' => 'open_page', 'page' => $siteAbout->public_id], $siteHero->state_json['primary_button']['action']);
        $this->assertSame(['type' => 'scroll_to', 'block' => $siteCta->public_id], $siteHero->state_json['secondary_button']['action']);
        $siteHeader = $siteAbout->blocks()->sole();
        $this->assertSame(['type' => 'open_page', 'page' => $siteHome->public_id], $siteHeader->state_json['menu'][0]['action']);
    }

    public function test_site_stays_independent_when_the_template_changes_and_is_republished(): void
    {
        $template = $this->template([SiteType::Landing]);
        $hero = $this->placeBlock($template->pages()->sole(), 'hero');
        $this->setState($hero, [...$hero->state_json, 'title' => 'Первая версия']);
        $this->publish($template);
        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();
        $siteHero = BlockInstance::query()->sole();

        $this->setState($hero, [...$hero->state_json, 'title' => 'Вторая версия']);
        $this->placeBlock($template->pages()->sole(), 'cta');
        $this->publish($template);

        $this->assertSame(['1.1.0', '1.0.0'], $template->versions()->latest('id')->pluck('version')->all());
        $this->assertSame(1, BlockInstance::query()->count(), 'republishing never adds Blocks to existing Sites');
        $this->assertSame('Первая версия', $siteHero->fresh()?->state_json['title']);

        $siteHero->update(['state_json' => [...$siteHero->state_json, 'title' => 'Правка клиента']]);
        $this->assertSame('Вторая версия', $hero->fresh()?->state_json['title'], 'Site edits never touch the Template');
    }

    public function test_legacy_version_without_content_starts_with_a_home_page(): void
    {
        $template = Template::factory()->published()->forSiteTypes(SiteType::Landing)->create();

        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();

        $home = Page::query()->sole();
        $this->assertSame([Page::HOME_TITLE, Page::HOME_SLUG, true], [$home->title, $home->slug, $home->is_home]);
        $this->assertSame(0, BlockInstance::query()->count());
    }

    public function test_unpublished_or_incompatible_templates_are_rejected_without_a_site(): void
    {
        $draft = $this->template([SiteType::Landing]);
        $this->placeBlock($draft->pages()->sole(), 'hero');
        $this->createSite($draft, SiteType::Landing)->assertSessionHasErrors(['template' => 'Выбранный шаблон недоступен.']);

        $landing = $this->template([SiteType::Landing]);
        $this->placeBlock($landing->pages()->sole(), 'hero');
        $this->publish($landing);
        $this->createSite($landing, SiteType::Quiz)->assertSessionHasErrors(['template' => 'Шаблон не подходит для выбранного формата сайта.']);

        $this->assertSame(0, Site::query()->count());
    }

    public function test_multi_page_template_installs_only_into_types_that_allow_pages(): void
    {
        $template = $this->template([SiteType::Landing, SiteType::MultiPage]);
        $this->placeBlock($template->pages()->sole(), 'hero');
        $this->addPage($template, 'Контакты', 'contacts');

        $this->assertContains(
            'Шаблон с несколькими страницами подходит только для многостраничных сайтов: уберите другие типы сайта или лишние страницы.',
            app(TemplatePublisher::class)->checks($template),
        );

        $template->forceFill(['site_types' => [SiteType::MultiPage->value]])->save();
        $this->publish($template);
        $template->forceFill(['site_types' => [SiteType::Landing->value, SiteType::MultiPage->value]])->save();

        $this->createSite($template, SiteType::Landing)
            ->assertSessionHasErrors(['template' => 'Шаблон содержит несколько страниц и подходит только для многостраничного сайта.']);
        $this->assertSame(0, Site::query()->count());

        $this->asCustomer()->get(route('sites.create'))
            ->assertInertia(fn (Assert $page) => $page->where('templates.0.site_types', ['multi_page']));
    }

    public function test_entitlement_template_follows_the_workspace_plan(): void
    {
        $template = $this->template([SiteType::Landing]);
        $this->placeBlock($template->pages()->sole(), 'hero');
        $this->publish($template);
        $template->forceFill(['access_mode' => CatalogAccessMode::Entitlement, 'access_entitlement' => Entitlement::RemoveBranding])->save();

        $this->createSite($template, SiteType::Landing)
            ->assertSessionHasErrors(['template' => 'Шаблон доступен на тарифе с опцией «Без брендинга Landflow».']);
        $this->asCustomer()->get(route('sites.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates.0.available', false)
                ->where('templates.0.access.restricted', true));

        $this->plan->setEntitlement(Entitlement::RemoveBranding, true);
        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();
        $this->assertSame(1, Site::query()->count());
    }

    public function test_paid_and_admin_grant_templates_need_a_workspace_license(): void
    {
        $paid = $this->template([SiteType::Landing]);
        $this->placeBlock($paid->pages()->sole(), 'hero');
        $this->publish($paid);
        $paid->forceFill(['access_mode' => CatalogAccessMode::Paid, 'site_price_minor' => 490_000, 'workspace_price_minor' => 1_490_000, 'price_currency' => 'RUB'])->save();

        $grant = $this->template([SiteType::Landing]);
        $this->placeBlock($grant->pages()->sole(), 'cta');
        $this->publish($grant);
        $grant->forceFill(['access_mode' => CatalogAccessMode::AdminGrant])->save();

        $this->createSite($paid, SiteType::Landing)
            ->assertSessionHasErrors(['template' => 'Платный шаблон: для нового сайта нужна лицензия на всё пространство. Покупка в Landflow пока недоступна.']);
        $this->createSite($grant, SiteType::Landing)
            ->assertSessionHasErrors(['template' => 'Шаблон выдаёт администратор Landflow; для нового сайта нужна лицензия на всё пространство.']);

        // A Site-scoped Template license stays valid data but cannot start a new Site.
        $existing = Site::factory()->create(['workspace_id' => $this->workspace->id]);
        CatalogLicense::factory()->forSite($existing)->ofTemplate($grant)->create();
        $this->createSite($grant, SiteType::Landing)->assertSessionHasErrors('template');
        // Another Workspace's license does not count.
        CatalogLicense::factory()->forWorkspace(Workspace::factory()->create())->ofTemplate($grant)->create();
        $this->createSite($grant, SiteType::Landing)->assertSessionHasErrors('template');
        $this->assertSame(1, Site::query()->count());

        CatalogLicense::factory()->forWorkspace($this->workspace)->ofTemplate($grant)->create();
        $this->createSite($grant, SiteType::Landing)->assertSessionHasNoErrors();
        $this->assertSame(2, Site::query()->count());
    }

    public function test_template_with_a_restricted_block_is_denied(): void
    {
        $block = $this->catalogBlock(['access_mode' => CatalogAccessMode::AdminGrant]);
        $template = $this->template([SiteType::Landing]);
        $this->placeVersion($template->pages()->sole(), $block->versions()->sole());
        $this->publish($template);

        $this->createSite($template, SiteType::Landing)
            ->assertSessionHasErrors(['template' => 'Шаблон содержит блок «Карточка по выдаче». Блок выдаёт администратор Landflow для сайта или всего пространства.']);
        $this->assertSame(0, Site::query()->count());

        // A Workspace license on the Block itself is enough.
        CatalogLicense::factory()->forWorkspace($this->workspace)->ofBlock($block)->create();
        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();
        $this->assertSame(1, Site::query()->count());
    }

    public function test_workspace_template_license_starts_an_independent_site_with_granted_versions(): void
    {
        $block = $this->catalogBlock(['access_mode' => CatalogAccessMode::AdminGrant]);
        $template = $this->template([SiteType::Landing]);
        $templateBlock = $this->placeVersion($template->pages()->sole(), $block->versions()->sole());
        $this->setState($templateBlock, ['title' => 'Из шаблона']);
        $this->placeBlock($template->pages()->sole(), 'hero');
        $this->publish($template);
        $template->forceFill(['access_mode' => CatalogAccessMode::AdminGrant])->save();
        CatalogLicense::factory()->forWorkspace($this->workspace)->ofTemplate($template)->create();

        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $instance = BlockInstance::query()->where('block_version_id', $block->versions()->sole()->id)->sole();
        $this->assertSame($site->id, $instance->page->site_id);
        $this->assertSame('Из шаблона', $instance->state_json['title']);

        // Copied content is independent of the Template.
        $this->setState($templateBlock, ['title' => 'Изменено в шаблоне']);
        $this->assertSame('Из шаблона', $instance->fresh()?->state_json['title']);

        // Every copied version is granted to this Site only; the Template license is not a Block license.
        $grants = app(BlockVersionGrants::class);
        $this->assertSame(
            $site->pages()->with('blocks')->get()->flatMap->blocks->pluck('block_version_id')->unique()->sort()->values()->all(),
            collect(array_keys($grants->grantedVersionIds($site)))->sort()->values()->all(),
        );
        $this->assertNotNull(app(BlockCatalogAccess::class)->denial($site, $block));
        $this->assertNotContains('block_access_denied', app(PublishValidator::class)->validate($site)->errorCodes());

        // Another Workspace without its own license is denied.
        [$stranger, $strangerWorkspace] = $this->otherCustomer();
        $this->createSiteAs($stranger, $strangerWorkspace, $template)
            ->assertSessionHasErrors(['template' => 'Шаблон выдаёт администратор Landflow; для нового сайта нужна лицензия на всё пространство.']);
        $this->assertSame(1, Site::query()->count());
    }

    public function test_template_license_covers_only_the_installed_version_blocks(): void
    {
        $first = $this->catalogBlock(['access_mode' => CatalogAccessMode::AdminGrant]);
        $template = $this->template([SiteType::Landing]);
        $this->placeVersion($template->pages()->sole(), $first->versions()->sole());
        $this->publish($template);

        // Version 2 swaps the block; the license must not union blocks across Template Versions.
        $template->pages()->sole()->blocks()->delete();
        $second = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'dev-second', 'name' => 'Вторая карточка', 'access_mode' => CatalogAccessMode::AdminGrant]);
        BlockVersion::factory()->sandboxed('<p>{{ title }}</p>')->for($second, 'definition')->create([
            'schema_json' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Текст', 'default' => 'Привет']]],
        ]);
        $this->placeVersion($template->pages()->sole(), $second->versions()->sole());
        $this->publish($template);
        $template->forceFill(['access_mode' => CatalogAccessMode::AdminGrant])->save();
        CatalogLicense::factory()->forWorkspace($this->workspace)->ofTemplate($template)->create();

        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $granted = app(BlockVersionGrants::class)->grantedVersionIds($site);
        $this->assertArrayHasKey($second->versions()->sole()->id, $granted);
        $this->assertArrayNotHasKey($first->versions()->sole()->id, $granted);
        $this->assertNotNull(app(BlockCatalogAccess::class)->denial($site, $first));
        $this->assertNotNull(app(BlockCatalogAccess::class)->workspaceDenial($this->workspace, $first));
    }

    public function test_installed_template_blocks_survive_later_restriction_and_revocation(): void
    {
        $block = $this->catalogBlock([]);
        $template = $this->template([SiteType::Landing]);
        $this->placeVersion($template->pages()->sole(), $block->versions()->sole());
        $this->publish($template);
        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $instance = BlockInstance::query()->where('block_version_id', $block->versions()->sole()->id)->sole();

        $block->forceFill(['access_mode' => CatalogAccessMode::AdminGrant])->save();
        $template->forceFill(['access_mode' => CatalogAccessMode::AdminGrant])->save();

        $this->assertNotContains('block_access_denied', app(PublishValidator::class)->validate($site)->errorCodes());
        $this->asCustomer()->post(route('sites.blocks.duplicate', [$site, $instance]))->assertSessionHasNoErrors();
        $this->assertSame(2, BlockInstance::query()->where('block_version_id', $block->versions()->sole()->id)->count());

        // New installs follow current access.
        $this->createSite($template, SiteType::Landing)->assertSessionHasErrors('template');
        $this->assertSame(1, Site::query()->count());
    }

    public function test_author_sets_template_access_and_others_cannot(): void
    {
        $template = $this->template([SiteType::Landing]);
        $author = $this->profile->user;

        $this->actingAs($author)->get(route('studio.templates.show', $template))
            ->assertInertia(fn (Assert $page) => $page
                ->where('access', ['mode' => 'free', 'entitlement' => null, 'site_price' => '', 'workspace_price' => ''])
                ->has('accessModes', 4));

        $this->actingAs($author)->put(route('studio.templates.access', $template), ['mode' => 'paid', 'site_price' => '4 900', 'workspace_price' => '14 900'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('studio.templates.show', $template));
        $template->refresh();
        $this->assertSame([CatalogAccessMode::Paid, 490_000, 1_490_000, 'RUB'], [$template->access_mode, $template->site_price_minor, $template->workspace_price_minor, $template->price_currency]);

        $this->actingAs($author)->put(route('studio.templates.access', $template), ['mode' => 'paid'])
            ->assertSessionHasErrors('site_price');
        $this->actingAs($author)->put(route('studio.templates.access', $template), ['mode' => 'entitlement', 'entitlement' => 'max_sites'])
            ->assertSessionHasErrors('entitlement');

        $stranger = DeveloperProfile::factory()->withPermissions()->create()->user;
        $this->actingAs($stranger)->put(route('studio.templates.access', $template), ['mode' => 'free'])->assertNotFound();
        $this->actingAs($this->customer)->put(route('studio.templates.access', $template), ['mode' => 'free'])->assertNotFound();
        $this->assertSame(CatalogAccessMode::Paid, $template->fresh()?->access_mode);
    }

    public function test_super_admin_grants_a_template_license(): void
    {
        $template = $this->template([SiteType::Landing]);
        $this->placeBlock($template->pages()->sole(), 'hero');
        $this->publish($template);
        $template->forceFill(['access_mode' => CatalogAccessMode::AdminGrant, 'name' => 'Шаблон по выдаче'])->save();
        $site = Site::factory()->create(['workspace_id' => $this->workspace->id, 'subdomain' => 'dealer']);
        $admin = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $admin->id, 'role' => PlatformRole::SuperAdmin->value]);

        $this->actingAs($admin)->get(route('platform.licenses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('items', fn ($items): bool => collect($items)->contains(fn ($item): bool => $item['kind'] === 'template' && $item['public_id'] === $template->public_id))
                ->missing('items.0.id'));

        $this->actingAs($admin)->post(route('platform.licenses.store'), ['template' => $template->public_id, 'scope' => 'workspace', 'target' => 'dealer'])
            ->assertSessionHasNoErrors();
        $license = CatalogLicense::query()->sole();
        $this->assertSame([null, $this->workspace->id, $template->id, null], [$license->site_id, $license->workspace_id, $license->template_id, $license->block_definition_id]);

        $this->actingAs($admin)->post(route('platform.licenses.store'), ['template' => $template->public_id, 'scope' => 'workspace', 'target' => $this->workspace->public_id])
            ->assertSessionHasErrors(['target' => 'У этого пространства уже есть лицензия на этот шаблон.']);
        $this->actingAs($admin)->post(route('platform.licenses.store'), ['template' => $template->public_id, 'scope' => 'site', 'target' => 'dealer'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('platform.licenses.store'), ['template' => $template->public_id, 'scope' => 'site', 'target' => $site->public_id])
            ->assertSessionHasErrors(['target' => 'У этого сайта уже есть лицензия на этот шаблон.']);
        $this->actingAs($admin)->post(route('platform.licenses.store'), ['template' => $template->public_id, 'block' => (string) Str::ulid(), 'scope' => 'site', 'target' => 'dealer'])
            ->assertSessionHasErrors('block');
        $this->assertSame(2, CatalogLicense::query()->count());

        $this->actingAs($admin)->get(route('platform.licenses.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('licenses.0.kind', 'template')
                ->where('licenses.0.item', 'Шаблон по выдаче')
                ->missing('licenses.0.template_id'));

        $this->createSite($template, SiteType::Landing)->assertSessionHasNoErrors();
    }

    public function test_create_page_lists_published_developer_templates_without_internal_ids(): void
    {
        $template = $this->template([SiteType::Landing]);
        $this->placeBlock($template->pages()->sole(), 'hero');
        $this->publish($template);
        $this->template([SiteType::Landing]);

        $this->asCustomer()->get(route('sites.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('templates', 1)
                ->where('templates.0.public_id', $template->public_id)
                ->where('templates.0.author', 'Студия А')
                ->where('templates.0.available', true)
                ->where('templates.0.reason', null)
                ->where('templates.0.access.restricted', false)
                ->missing('templates.0.id')
                ->missing('templates.0.developer_profile_id')
                ->missing('templates.0.latest_version'));
    }

    /**
     * @param  list<SiteType>  $types
     */
    private function template(array $types): Template
    {
        $template = Template::factory()->developer($this->profile)->forSiteTypes(...$types)->create();
        $home = new TemplatePage(['title' => 'Главная', 'slug' => 'home', 'sort_order' => 0]);
        $home->is_home = true;
        $home->template()->associate($template);
        $home->save();

        return $template;
    }

    private function addPage(Template $template, string $title, string $slug): TemplatePage
    {
        $page = new TemplatePage(['title' => $title, 'slug' => $slug, 'sort_order' => $template->pages()->count()]);
        $page->template()->associate($template);
        $page->save();

        return $page;
    }

    private function placeBlock(TemplatePage $page, string $slug): TemplateBlock
    {
        return $this->placeVersion($page, BlockDefinition::query()->where('slug', $slug)->sole()->versions()->latest('id')->firstOrFail());
    }

    private function placeVersion(TemplatePage $page, BlockVersion $version): TemplateBlock
    {
        $block = new TemplateBlock(['sort_order' => $page->blocks()->count(), 'state_json' => app(BlockStateDefaults::class)->fromSchema($version->schema_json)]);
        $block->page()->associate($page);
        $block->version()->associate($version);
        $block->save();

        return $block;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function setState(TemplateBlock $block, array $state): void
    {
        $block->update(['state_json' => $state]);
        $block->refresh();
    }

    private function publish(Template $template): void
    {
        $this->actingAs($this->profile->user)->post(route('studio.templates.publish', $template))->assertSessionHasNoErrors();
    }

    /**
     * @param  array<string, mixed>  $access
     */
    private function catalogBlock(array $access): BlockDefinition
    {
        $definition = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'dev-grant', 'name' => 'Карточка по выдаче', ...$access]);
        BlockVersion::factory()->sandboxed('<p>{{ title }}</p>')->for($definition, 'definition')->create([
            'schema_json' => ['fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Текст', 'default' => 'Привет']]],
        ]);

        return $definition;
    }

    private function createSite(Template $template, SiteType $type): TestResponse
    {
        return $this->asCustomer()->post(route('sites.store'), [
            'name' => 'Автосалон из шаблона',
            'site_type' => $type->value,
            'start' => 'template',
            'template' => $template->public_id,
        ]);
    }

    /**
     * @return array{0: User, 1: Workspace}
     */
    private function otherCustomer(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['plan_id' => $this->plan->id]);
        $workspace->addMember($user, WorkspaceRole::Owner);

        return [$user, $workspace];
    }

    private function createSiteAs(User $user, Workspace $workspace, Template $template): TestResponse
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id])->post(route('sites.store'), [
            'name' => 'Чужой сайт из шаблона',
            'site_type' => SiteType::Landing->value,
            'start' => 'template',
            'template' => $template->public_id,
        ]);
    }

    private function asCustomer(): static
    {
        return $this->actingAs($this->customer)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
