<?php

namespace Tests\Feature\Sites;

use App\Enums\SiteSectionType;
use App\Enums\SiteType;
use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteSection;
use App\Models\SiteSectionRedirect;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteSectionsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create(['site_type' => SiteType::MultiPage, 'public_number' => self::DEALER_NUMBER]);
        Page::factory()->for($this->site)->home()->create();
        Page::factory()->for($this->site)->create(['title' => 'Кредит', 'slug' => 'credit']);
    }

    public function test_owner_sees_sections_page_with_available_types(): void
    {
        $this->as($this->owner)->get(route('sites.sections.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/sections')
                ->has('sections', 0)
                ->has('types', 3)
                ->where('types.0.value', 'catalog')
                ->where('types.0.available', true)
                ->where('types.1.available', false)
                ->where('baseUrl', 'http://localhost/100000000001/')
                ->where('siteContext.can.manageSections', true));
    }

    public function test_owner_adds_catalog_section_with_default_prefix(): void
    {
        $this->as($this->owner)->post(route('sites.sections.store', $this->site), ['type' => 'catalog'])
            ->assertRedirect(route('sites.sections.index', $this->site));

        $section = $this->site->sections()->sole();
        $this->assertSame(SiteSectionType::Catalog, $section->type);
        $this->assertSame('catalog', $section->prefix);
        $this->assertSame('Каталог автомобилей', $section->title);
        $this->assertTrue($section->enabled);

        $this->as($this->owner)->post(route('sites.sections.store', $this->site), ['type' => 'catalog'])
            ->assertSessionHasErrors(['type' => 'Такой раздел уже есть на сайте.']);
    }

    public function test_stock_sections_are_not_available_yet(): void
    {
        $this->as($this->owner)->post(route('sites.sections.store', $this->site), ['type' => 'used'])
            ->assertSessionHasErrors('type');

        $this->assertSame(0, $this->site->sections()->count());
    }

    public function test_default_prefix_taken_by_a_page_asks_for_another_one(): void
    {
        Page::factory()->for($this->site)->create(['slug' => 'catalog']);

        $this->as($this->owner)->post(route('sites.sections.store', $this->site), ['type' => 'catalog'])
            ->assertSessionHasErrors(['prefix' => 'Такой адрес уже занят страницей сайта. Укажите адрес раздела вручную.']);

        $this->as($this->owner)->post(route('sites.sections.store', $this->site), ['type' => 'catalog', 'prefix' => 'Autos'])
            ->assertSessionHasNoErrors();
        $this->assertSame('autos', $this->site->sections()->sole()->prefix);
    }

    public function test_renaming_prefix_keeps_the_old_one_as_redirect(): void
    {
        $section = SiteSection::factory()->for($this->site)->create();

        $this->update($section, 'autos')->assertSessionHasNoErrors();
        $this->assertSame('autos', $section->refresh()->prefix);
        $this->assertSame(['catalog'], $section->redirects()->pluck('prefix')->all());

        $this->as($this->owner)->get(route('sites.sections.index', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sections.0.prefix', 'autos')
                ->where('sections.0.redirects', ['catalog'])
                ->missing('sections.0.id'));

        // Returning to a previous prefix makes it current again; the abandoned one redirects.
        $this->update($section, 'catalog')->assertSessionHasNoErrors();
        $this->assertSame(['autos'], $section->redirects()->pluck('prefix')->all());
    }

    public function test_invalid_reserved_and_taken_prefixes_are_refused(): void
    {
        $section = SiteSection::factory()->for($this->site)->create();

        $this->update($section, 'Каталог')->assertSessionHasErrors('prefix');
        $this->update($section, 'auto_catalog')->assertSessionHasErrors('prefix');
        $this->update($section, str_repeat('a', 61))->assertSessionHasErrors('prefix');
        $this->update($section, 'sitemap')->assertSessionHasErrors(['prefix' => 'Этот адрес зарезервирован, выберите другой.']);
        $this->update($section, 'home')->assertSessionHasErrors('prefix');
        $this->update($section, 'credit')->assertSessionHasErrors(['prefix' => 'Такой адрес уже занят страницей сайта.']);

        $this->assertSame('catalog', $section->refresh()->prefix);
        $this->assertSame(0, SiteSectionRedirect::query()->count());
    }

    public function test_pages_cannot_take_a_section_prefix_but_may_take_a_redirect(): void
    {
        $section = SiteSection::factory()->for($this->site)->create();

        $this->as($this->owner)->post(route('sites.pages.store', $this->site), ['title' => 'Каталог', 'slug' => 'catalog'])
            ->assertSessionHasErrors(['slug' => 'Такой адрес уже занят разделом сайта.']);

        $this->as($this->owner)->post(route('sites.pages.store', $this->site), ['title' => 'Catalog'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->site->pages()->where('slug', 'catalog-2')->exists());

        $this->update($section, 'autos');
        $this->as($this->owner)->post(route('sites.pages.store', $this->site), ['title' => 'Старый каталог', 'slug' => 'catalog'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $section->redirects()->count());
    }

    public function test_title_and_visibility_are_saved_and_section_can_be_deleted(): void
    {
        $section = SiteSection::factory()->for($this->site)->create();

        $this->as($this->owner)->patch(route('sites.sections.update', [$this->site, $section]), [
            'title' => 'Новые автомобили',
            'prefix' => 'catalog',
            'enabled' => false,
        ])->assertSessionHasNoErrors();

        $section->refresh();
        $this->assertSame('Новые автомобили', $section->title);
        $this->assertFalse($section->enabled);
        $this->assertSame(0, $section->redirects()->count());

        $this->update($section, 'autos');
        $this->as($this->owner)->delete(route('sites.sections.destroy', [$this->site, $section]))
            ->assertRedirect(route('sites.sections.index', $this->site));
        $this->assertSame(0, SiteSection::query()->count());
        $this->assertSame(0, SiteSectionRedirect::query()->count());
    }

    public function test_landing_quiz_and_chat_sites_have_no_sections(): void
    {
        foreach ([SiteType::Landing, SiteType::Quiz, SiteType::ChatSelection] as $type) {
            $site = Site::factory()->for($this->workspace)->create(['site_type' => $type]);

            $this->as($this->owner)->get(route('sites.sections.index', $site))->assertForbidden();
            $this->as($this->owner)->post(route('sites.sections.store', $site), ['type' => 'catalog'])->assertForbidden();
            $this->as($this->owner)->get(route('sites.show', $site))
                ->assertInertia(fn (Assert $page) => $page->where('siteContext.can.manageSections', false));
        }
    }

    public function test_permissions_and_tenant_isolation(): void
    {
        $section = SiteSection::factory()->for($this->site)->create();
        $editor = $this->member(WorkspaceRole::ContentEditor);

        $this->as($editor)->get(route('sites.sections.index', $this->site))->assertForbidden();
        $this->as($editor)->patch(route('sites.sections.update', [$this->site, $section]), ['title' => 'X', 'prefix' => 'x', 'enabled' => true])
            ->assertForbidden();

        $designer = $this->member(WorkspaceRole::Designer);
        $this->as($designer)->get(route('sites.sections.index', $this->site))->assertOk();

        $otherSite = Site::factory()->for($this->workspace)->create(['site_type' => SiteType::MultiPage]);
        $this->as($this->owner)->delete(route('sites.sections.destroy', [$otherSite, $section]))->assertNotFound();

        $stranger = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $foreign->addMember($stranger, WorkspaceRole::Owner);
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->get(route('sites.sections.index', $this->site))->assertNotFound();

        $this->assertSame(1, SiteSection::query()->count());
    }

    private function update(SiteSection $section, string $prefix): TestResponse
    {
        return $this->as($this->owner)->patch(route('sites.sections.update', [$this->site, $section]), [
            'title' => $section->title,
            'prefix' => $prefix,
            'enabled' => true,
        ]);
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }

    private function member(WorkspaceRole $role): User
    {
        $user = User::factory()->create();
        $this->workspace->addMember($user, $role);

        return $user;
    }
}
