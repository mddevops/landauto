<?php

namespace Tests\Feature\Sites;

use App\Enums\Entitlement;
use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SiteSeoPageTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $site;

    private Page $home;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create(['name' => 'Дилер', 'subdomain' => 'dealer']);
        $this->home = Page::factory()->for($this->site)->home()->create(['title' => 'Главная', 'seo_title' => 'KIA в Москве']);
        Page::factory()->for($this->site)->create(['title' => 'Акции', 'slug' => 'promo', 'sort_order' => 2]);
    }

    public function test_owner_sees_every_page_and_search_addresses(): void
    {
        $this->as($this->owner)->get(route('sites.seo.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/seo')
                ->where('site.public_id', $this->site->public_id)
                ->has('pages', 2)
                ->where('pages.0.public_id', $this->home->public_id)
                ->where('pages.0.path', '/')
                ->where('pages.0.seo_title', 'KIA в Москве')
                ->where('pages.1.path', '/promo')
                ->where('addresses.primary', 'http://dealer.localhost/')
                ->where('addresses.sitemap', null)
                ->where('published', false)
                ->where('can.editIndexing', true)
                ->where('siteContext.can.editSeo', true)
                ->missing('pages.0.id'));
    }

    public function test_search_addresses_use_the_custom_primary_domain(): void
    {
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::CustomDomain, true);
        $this->workspace->plan()->associate($plan)->save();
        SiteDomain::factory()->for($this->site)->primary()->create(['hostname' => 'dealer.ru']);
        $this->site->forceFill(['active_published_version_id' => null])->save();

        $this->as($this->owner)->get(route('sites.seo.index', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('addresses.primary', 'http://dealer.ru/'));
    }

    public function test_saving_from_the_seo_page_returns_to_it(): void
    {
        $this->as($this->owner)
            ->patch(route('sites.pages.seo.update', ['site' => $this->site, 'page' => $this->home]), [
                'seo_title' => 'Новый заголовок',
                'seo_description' => 'Описание',
                'seo_noindex' => true,
                'return' => 'seo',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.seo.index', $this->site));

        $home = $this->home->fresh();
        $this->assertSame('Новый заголовок', $home?->seo_title);
        $this->assertTrue($home?->seo_noindex);

        $this->as($this->owner)
            ->patch(route('sites.pages.seo.update', ['site' => $this->site, 'page' => $this->home]), ['return' => 'https://evil.test'])
            ->assertSessionHasErrors('return');
    }

    public function test_content_editor_edits_title_and_description_but_not_indexing(): void
    {
        $editor = $this->member(WorkspaceRole::ContentEditor);

        $this->as($editor)->get(route('sites.seo.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can.editIndexing', false)->where('siteContext.can.editSeo', true));

        $this->as($editor)
            ->patch(route('sites.pages.seo.update', ['site' => $this->site, 'page' => $this->home]), [
                'seo_title' => 'От редактора',
                'seo_noindex' => true,
                'return' => 'seo',
            ])
            ->assertRedirect(route('sites.seo.index', $this->site));

        $this->assertSame('От редактора', $this->home->fresh()?->seo_title);
        $this->assertFalse($this->home->fresh()?->seo_noindex);
    }

    public function test_designer_and_foreign_workspaces_cannot_open_seo(): void
    {
        $designer = $this->member(WorkspaceRole::Designer);

        $this->as($designer)->get(route('sites.seo.index', $this->site))->assertForbidden();
        $this->as($designer)->get(route('sites.show', $this->site))
            ->assertInertia(fn (Assert $page) => $page->where('siteContext.can.editSeo', false));

        $stranger = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $foreign->addMember($stranger, WorkspaceRole::Owner);
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->get(route('sites.seo.index', $this->site))
            ->assertNotFound();
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
