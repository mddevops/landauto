<?php

namespace Tests\Feature\Templates;

use App\Blocks\BlockStateDefaults;
use App\Enums\DeveloperPermission;
use App\Enums\PlatformRole;
use App\Enums\TemplateOwnerScope;
use App\Enums\WorkspaceRole;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\DeveloperProfile;
use App\Models\PlatformRoleAssignment;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Mockery;
use Tests\TestCase;

class TemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    private DeveloperProfile $profile;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->profile = DeveloperProfile::factory()->withPermissions()->create(['display_name' => 'Студия А']);
        $this->developer = $this->profile->user;
    }

    public function test_developer_creates_a_template_owned_by_their_profile_with_a_home_page(): void
    {
        Log::spy();
        $other = DeveloperProfile::factory()->withPermissions()->create();

        $this->actingAs($this->developer)->get(route('developer.templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('developer/templates/index')->has('templates', 0));
        $this->actingAs($this->developer)->get(route('developer.templates.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('developer/templates/create')->has('siteTypes', 4));

        $response = $this->actingAs($this->developer)->post(route('developer.templates.store'), [
            'name' => ' Дилерский лендинг ',
            'slug' => 'dealer-landing',
            'site_types' => ['landing', 'quiz'],
            'owner_scope' => 'platform',
            'developer_profile_id' => $other->id,
            'is_official' => true,
        ]);

        $template = Template::query()->sole();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('studio.templates.designer', $template->public_id))
            ->assertInertiaFlash('toast.message', 'Шаблон «Дилерский лендинг» создан.');

        $this->assertSame(TemplateOwnerScope::Developer, $template->owner_scope);
        $this->assertSame($this->profile->id, $template->developer_profile_id);
        $this->assertSame($this->developer->id, $template->created_by_user_id);
        $this->assertFalse($template->is_official, 'a new Template is not offered for Site creation');
        $this->assertSame(['landing', 'quiz'], $template->site_types);
        $this->assertTrue(Str::isUlid($template->public_id));
        $this->assertSame([['Главная', 'home', true]], $template->pages()->get()->map(fn (TemplatePage $page): array => [$page->title, $page->slug, $page->is_home])->all());
        $this->assertSame(0, TemplateVersion::query()->count());

        Log::shouldHaveReceived('info')->with('developer.template_created', Mockery::on(fn (array $context): bool => $context === [
            'template' => $template->public_id,
            'owner_scope' => 'developer',
            'actor_user_id' => $this->developer->id,
        ]));

        $this->actingAs($this->developer)->get(route('developer.templates.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('templates', 1)
                ->where('templates.0.public_id', $template->public_id)
                ->where('templates.0.site_type_labels', ['Лендинг', 'Квиз'])
                ->where('templates.0.latest_version', null)
                ->missing('templates.0.id')
                ->missing('templates.0.developer_profile_id'));

        $this->assertSame(0, Template::query()->ownedByDeveloper($other)->count());
    }

    public function test_create_validation_requires_unique_slug_and_known_site_types(): void
    {
        Template::factory()->create(['slug' => 'taken-slug']);

        $this->actingAs($this->developer)->post(route('developer.templates.store'), [
            'name' => '',
            'slug' => 'taken-slug',
            'site_types' => ['spaceship'],
        ])->assertSessionHasErrors(['name', 'slug', 'site_types.0']);

        $this->actingAs($this->developer)->post(route('developer.templates.store'), [
            'name' => 'Шаблон',
            'slug' => 'Bad Slug',
            'site_types' => [],
        ])->assertSessionHasErrors(['slug', 'site_types']);

        $this->assertSame(1, Template::query()->count());
    }

    public function test_super_admin_creates_platform_templates_and_others_cannot(): void
    {
        $superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);

        $this->actingAs($superAdmin)->get(route('platform.templates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('platform/templates/index'));
        $this->actingAs($superAdmin)->post(route('platform.templates.store'), [
            'name' => 'Официальный',
            'slug' => 'official-one',
            'site_types' => ['multi_page'],
        ])->assertSessionHasNoErrors();

        $template = Template::query()->sole();
        $this->assertSame(TemplateOwnerScope::Platform, $template->owner_scope);
        $this->assertNull($template->developer_profile_id);

        foreach ([$this->userWithRole(PlatformRole::CatalogManager), $this->developer, $this->customer()] as $user) {
            $this->actingAs($user)->get(route('platform.templates.index'))->assertForbidden();
            $this->actingAs($user)->post(route('platform.templates.store'), ['name' => 'X', 'slug' => 'x-template', 'site_types' => ['landing']])->assertForbidden();
            $this->actingAs($user)->get(route('studio.templates.show', $template))->assertNotFound();
            $this->actingAs($user)->get(route('studio.templates.designer', $template))->assertNotFound();
        }

        $this->assertSame(1, Template::query()->count());
    }

    public function test_only_the_owning_developer_edits_a_developer_template(): void
    {
        $template = $this->developerTemplate();
        $page = $template->pages()->sole();
        $block = $this->placeBlock($page, 'hero');

        $stranger = DeveloperProfile::factory()->withPermissions()->create()->user;
        $withoutPermission = DeveloperProfile::factory()->withPermissions(DeveloperPermission::CreateBlocks)->create()->user;
        $suspended = DeveloperProfile::factory()->withPermissions()->suspended()->create();
        $suspendedTemplate = Template::factory()->developer($suspended)->create();
        $superAdmin = $this->userWithRole(PlatformRole::SuperAdmin);

        foreach ([$stranger, $withoutPermission, $superAdmin, $this->customer()] as $user) {
            $this->actingAs($user)->get(route('studio.templates.show', $template))->assertNotFound();
            $this->actingAs($user)->get(route('studio.templates.designer', $template))->assertNotFound();
            $this->actingAs($user)->get(route('studio.templates.preview.frame', $template))->assertNotFound();
            $this->actingAs($user)->patch(route('studio.templates.update', $template), ['name' => 'Чужое', 'site_types' => ['landing']])->assertNotFound();
            $this->actingAs($user)->post(route('studio.templates.publish', $template))->assertNotFound();
            $this->actingAs($user)->post(route('studio.templates.pages.store', $template), ['title' => 'Чужая'])->assertNotFound();
            $this->actingAs($user)->post(route('studio.templates.blocks.store', [$template, $page->public_id]), ['block' => 'hero'])->assertNotFound();
            $this->actingAs($user)->patch(route('studio.templates.blocks.state', [$template, $block->public_id]), ['state' => ['title' => 'Взлом']])->assertNotFound();
            $this->actingAs($user)->delete(route('studio.templates.blocks.destroy', [$template, $block->public_id]))->assertNotFound();
        }

        $this->actingAs($withoutPermission)->get(route('developer.templates.index'))->assertForbidden();
        $this->actingAs($suspended->user)->get(route('studio.templates.show', $suspendedTemplate))->assertNotFound();

        $this->assertSame(1, $template->pages()->count());
        $this->assertNotSame('Взлом', $block->fresh()?->state_json['title']);
        $this->assertSame(0, TemplateVersion::query()->count());

        $foreignTemplate = $this->developerTemplate(DeveloperProfile::factory()->withPermissions()->create());
        $foreignPage = $foreignTemplate->pages()->sole();
        $this->actingAs($this->developer)->post(route('studio.templates.blocks.store', [$template, $foreignPage->public_id]), ['block' => 'hero'])->assertNotFound();
        $this->assertSame(0, $foreignPage->blocks()->count());
    }

    public function test_designer_edits_pages_and_blocks_of_published_catalog_blocks_only(): void
    {
        $template = $this->developerTemplate();
        $home = $template->pages()->sole();
        $draftOnly = BlockDefinition::factory()->developer($this->profile)->create(['slug' => 'draft-only']);
        $private = BlockDefinition::factory()->workspacePrivate()->create(['slug' => 'private-block']);
        BlockVersion::factory()->for($private, 'definition')->create();
        $partner = BlockDefinition::factory()->developer()->create(['slug' => 'partner-block', 'name' => 'Партнёрский']);
        BlockVersion::factory()->for($partner, 'definition')->sandboxed()->create();

        $this->actingAs($this->developer)->get(route('studio.templates.designer', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('studio/templates/designer')
                ->where('template.public_id', $template->public_id)
                ->where('page.public_id', $home->public_id)
                ->has('pages', 1)
                ->has('blocks', 0)
                ->where('library', fn ($library): bool => collect($library)->pluck('slug')->contains('partner-block')
                    && collect($library)->pluck('slug')->contains('hero')
                    && ! collect($library)->pluck('slug')->contains('draft-only')
                    && ! collect($library)->pluck('slug')->contains('private-block'))
                ->missing('template.id')
                ->missing('template.developer_profile_id'));

        foreach (['draft-only', 'private-block', 'missing-block'] as $slug) {
            $this->actingAs($this->developer)
                ->post(route('studio.templates.blocks.store', [$template, $home->public_id]), ['block' => $slug])
                ->assertSessionHasErrors(['block' => 'Этот блок недоступен.']);
        }
        $this->assertSame(0, $home->blocks()->count());
        $this->assertSame(0, $draftOnly->versions()->count());

        $this->actingAs($this->developer)
            ->post(route('studio.templates.blocks.store', [$template, $home->public_id]), ['block' => 'hero'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->developer)
            ->post(route('studio.templates.blocks.store', [$template, $home->public_id]), ['block' => 'partner-block'])
            ->assertSessionHasNoErrors();
        [$hero, $partnerBlock] = $home->blocks()->get()->all();
        $this->assertSame('hero', $hero->version->definition->slug);
        $this->assertSame($partner->versions()->sole()->id, $partnerBlock->block_version_id);

        $this->actingAs($this->developer)->patch(route('studio.templates.blocks.state', [$template, $hero->public_id]), ['state' => [
            'title' => 'Новые автомобили',
            'align' => 'center',
            'primary_button' => ['label' => 'Подобрать'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('Новые автомобили', $hero->fresh()?->state_json['title']);

        $this->actingAs($this->developer)->patch(route('studio.templates.blocks.state', [$template, $hero->public_id]), ['state' => [
            'title' => str_repeat('я', 121),
            'align' => 'right',
            'secret' => 'x',
        ]])->assertSessionHasErrors(['state.title', 'state.align', 'state.secret']);
        $this->assertSame('Новые автомобили', $hero->fresh()?->state_json['title']);

        $this->actingAs($this->developer)->post(route('studio.templates.blocks.move', [$template, $partnerBlock->public_id]), ['direction' => 'up'])->assertSessionHasNoErrors();
        $this->assertSame([$partnerBlock->id, $hero->id], $home->blocks()->pluck('id')->all());
        $this->actingAs($this->developer)->post(route('studio.templates.blocks.duplicate', [$template, $hero->public_id]))->assertSessionHasNoErrors();
        $this->assertSame(3, $home->blocks()->count());
        $this->actingAs($this->developer)->patch(route('studio.templates.blocks.visibility', [$template, $partnerBlock->public_id]), ['hidden' => true])->assertSessionHasNoErrors();
        $this->assertTrue($partnerBlock->fresh()?->is_hidden);
        $this->actingAs($this->developer)->delete(route('studio.templates.blocks.destroy', [$template, $partnerBlock->public_id]))->assertSessionHasNoErrors();
        $this->assertSame(2, $home->blocks()->count());

        $this->actingAs($this->developer)->post(route('studio.templates.pages.store', $template), ['title' => 'О компании'])->assertSessionHasNoErrors();
        $about = $template->pages()->where('slug', 'o-kompanii')->sole();
        $this->actingAs($this->developer)->post(route('studio.templates.pages.store', $template), ['title' => 'Дубль', 'slug' => 'o-kompanii'])
            ->assertSessionHasErrors(['slug' => 'Страница с таким адресом уже есть в шаблоне.']);
        $this->actingAs($this->developer)->patch(route('studio.templates.pages.update', [$template, $about->public_id]), ['title' => 'Контакты', 'slug' => 'contacts'])->assertSessionHasNoErrors();
        $this->assertSame(['Контакты', 'contacts'], [$about->fresh()?->title, $about->fresh()?->slug]);
        $this->actingAs($this->developer)->delete(route('studio.templates.pages.destroy', [$template, $home->public_id]))
            ->assertSessionHasErrors(['page' => 'Главную страницу шаблона удалить нельзя.']);
        $this->actingAs($this->developer)->delete(route('studio.templates.pages.destroy', [$template, $about->public_id]))->assertSessionHasNoErrors();
        $this->assertSame(1, $template->pages()->count());

        $this->actingAs($this->developer)->get(route('studio.templates.designer', $template))
            ->assertInertia(fn (Assert $page) => $page
                ->has('blocks', 2)
                ->where('blocks.0.slug', 'hero')
                ->missing('blocks.0.id')
                ->missing('blocks.0.block_version_id'));
    }

    public function test_template_block_refuses_workspace_private_definitions(): void
    {
        $template = $this->developerTemplate();
        $page = $template->pages()->sole();
        $private = BlockVersion::factory()->for(BlockDefinition::factory()->workspacePrivate(), 'definition')->create();

        $block = new TemplateBlock(['sort_order' => 0, 'state_json' => []]);
        $block->page()->associate($page);
        $block->version()->associate($private);

        $this->expectException(LogicException::class);
        $block->save();
    }

    public function test_preview_and_frame_render_the_draft_with_visible_blocks_only(): void
    {
        $template = $this->developerTemplate();
        $page = $template->pages()->sole();
        $visible = $this->placeBlock($page, 'hero');
        $hidden = $this->placeBlock($page, 'footer');
        TemplateBlock::query()->whereKey($hidden->id)->update(['is_hidden' => true]);

        $this->actingAs($this->developer)->get(route('studio.templates.preview', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('studio/templates/preview')
                ->where('page.public_id', $page->public_id)
                ->has('pages', 1));

        $this->actingAs($this->developer)->get(route('studio.templates.preview.frame', ['template' => $template, 'page' => $page->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('studio/templates/frame')
                ->has('blocks', 1)
                ->where('blocks.0.public_id', $visible->public_id));

        $this->actingAs($this->developer)->get(route('studio.templates.preview', ['template' => $template, 'page' => (string) Str::ulid()]))->assertNotFound();
    }

    public function test_publishing_runs_automated_checks_and_creates_immutable_versions(): void
    {
        Log::spy();
        $template = $this->developerTemplate(siteTypes: []);
        $page = $template->pages()->sole();

        $this->actingAs($this->developer)->get(route('studio.templates.show', $template))
            ->assertOk()
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->component('studio/templates/show')
                ->where('template.owner_scope', 'developer')
                ->where('checks', [
                    'Выберите хотя бы один тип сайта в настройках шаблона.',
                    'Добавьте на страницы шаблона хотя бы один блок.',
                ])
                ->has('versions', 0)
                ->missing('template.id'));

        $this->actingAs($this->developer)->post(route('studio.templates.publish', $template))->assertSessionHasErrors('template');
        $this->assertSame(0, TemplateVersion::query()->count());

        $this->actingAs($this->developer)->patch(route('studio.templates.update', $template), ['name' => 'Лендинг дилера', 'site_types' => ['landing']])->assertSessionHasNoErrors();
        $hero = $this->placeBlock($page, 'hero');

        $this->actingAs($this->developer)->post(route('studio.templates.publish', $template))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'Опубликована версия 1.0.0.');

        $first = TemplateVersion::query()->sole();
        $this->assertSame('1.0.0', $first->version);
        $this->assertSame($this->developer->id, $first->published_by_user_id);
        $this->assertSame([[
            'key' => $page->public_id,
            'title' => 'Главная',
            'slug' => 'home',
            'is_home' => true,
            'blocks' => [[
                'key' => $hero->public_id,
                'block_version_id' => $hero->block_version_id,
                'is_hidden' => false,
                'state' => $hero->state_json,
            ]],
        ]], $first->content_json['pages'] ?? null);
        Log::shouldHaveReceived('info')->with('developer.template_published', Mockery::on(fn (array $context): bool => $context['version'] === '1.0.0'));

        $this->actingAs($this->developer)->post(route('studio.templates.publish', $template))
            ->assertSessionHasErrors(['template' => 'Изменений с версии 1.0.0 нет.']);

        $this->actingAs($this->developer)->patch(route('studio.templates.blocks.state', [$template, $hero->public_id]), ['state' => [
            'title' => 'Черновик после публикации',
            'align' => 'left',
            'primary_button' => ['label' => 'Подобрать'],
        ]])->assertSessionHasNoErrors();
        $this->assertNotSame('Черновик после публикации', $first->fresh()?->content_json['pages'][0]['blocks'][0]['state']['title'], 'autosave never changes a published version');

        $this->actingAs($this->developer)->post(route('studio.templates.publish', $template))->assertSessionHasNoErrors();
        $this->assertSame(['1.1.0', '1.0.0'], $template->versions()->latest('id')->pluck('version')->all());

        $this->actingAs($this->developer)->get(route('studio.templates.show', $template))
            ->assertInertia(fn (Assert $inertia) => $inertia
                ->where('checks', [])
                ->has('versions', 2)
                ->where('versions.0.version', '1.1.0')
                ->missing('versions.0.id')
                ->missing('versions.0.content_json'));

        $this->assertFalse($template->fresh()?->is_official, 'publishing does not offer the Template for Site creation (P9-015)');

        $this->expectException(LogicException::class);
        $first->update(['version' => '9.9.9']);
    }

    public function test_template_versions_cannot_be_deleted(): void
    {
        $template = $this->developerTemplate();
        $this->placeBlock($template->pages()->sole(), 'hero');
        $this->actingAs($this->developer)->post(route('studio.templates.publish', $template))->assertSessionHasNoErrors();

        $this->expectException(LogicException::class);
        TemplateVersion::query()->sole()->delete();
    }

    public function test_settings_never_change_slug_or_ownership(): void
    {
        $template = $this->developerTemplate();
        $other = DeveloperProfile::factory()->create();

        $this->actingAs($this->developer)->patch(route('studio.templates.update', $template), [
            'name' => 'Новое имя',
            'site_types' => ['multi_page', 'chat_selection'],
            'slug' => 'stolen',
            'owner_scope' => 'platform',
            'developer_profile_id' => $other->id,
        ])->assertSessionHasNoErrors();

        $fresh = $template->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(['Новое имя', ['multi_page', 'chat_selection']], [$fresh->name, $fresh->site_types]);
        $this->assertSame([$template->slug, TemplateOwnerScope::Developer, $this->profile->id], [$fresh->slug, $fresh->owner_scope, $fresh->developer_profile_id]);
    }

    /**
     * @param  list<string>  $siteTypes
     */
    private function developerTemplate(?DeveloperProfile $profile = null, array $siteTypes = ['landing']): Template
    {
        $template = Template::factory()->developer($profile ?? $this->profile)->create(['site_types' => $siteTypes]);
        $home = new TemplatePage(['title' => 'Главная', 'slug' => 'home', 'sort_order' => 0]);
        $home->is_home = true;
        $home->template()->associate($template);
        $home->save();

        return $template;
    }

    private function placeBlock(TemplatePage $page, string $slug): TemplateBlock
    {
        $version = BlockDefinition::query()->where('slug', $slug)->sole()->versions()->latest('id')->firstOrFail();
        $block = new TemplateBlock(['sort_order' => $page->blocks()->count(), 'state_json' => app(BlockStateDefaults::class)->fromSchema($version->schema_json)]);
        $block->page()->associate($page);
        $block->version()->associate($version);
        $block->save();

        return $block;
    }

    private function userWithRole(PlatformRole $role): User
    {
        $user = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);

        return $user;
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        Workspace::factory()->create()->addMember($user, WorkspaceRole::Owner);

        return $user;
    }
}
