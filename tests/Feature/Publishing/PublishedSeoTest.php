<?php

namespace Tests\Feature\Publishing;

use App\Enums\WorkspaceRole;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Publishing\PublishSite;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class PublishedSeoTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
    }

    public function test_published_pages_carry_title_description_canonical_robots_and_real_open_graph_only(): void
    {
        $this->home->forceFill(['seo_title' => 'KIA в Москве — Дилер', 'seo_description' => 'Новые KIA в наличии.'])->save();
        $offers = $this->offersPage(noindex: true);
        $this->publish();

        $this->visit('/')
            ->assertOk()
            ->assertSee('<title>KIA в Москве — Дилер</title>', false)
            ->assertSee('<meta name="description" content="Новые KIA в наличии.">', false)
            ->assertSee('<link rel="canonical" href="http://dealer.localhost/">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<meta property="og:title" content="KIA в Москве — Дилер">', false)
            ->assertSee('<meta property="og:url" content="http://dealer.localhost/">', false)
            ->assertSee('<meta property="og:site_name" content="Дилер">', false)
            ->assertDontSee('og:image', false);

        $this->visit('/'.$offers->slug)
            ->assertOk()
            ->assertSee('<title>Предложения</title>', false)
            ->assertSee('<link rel="canonical" href="http://dealer.localhost/offers">', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertDontSee('name="description"', false)
            ->assertDontSee('og:description', false);
    }

    public function test_sitemap_lists_only_indexable_pages_of_the_active_version(): void
    {
        $this->offersPage(noindex: true);
        $promo = Page::factory()->for($this->site)->create(['slug' => 'promo', 'title' => 'Акции', 'sort_order' => 3]);
        $this->place('cta', ['title' => 'Акция'], page: $promo);
        $this->publish();
        Page::factory()->for($this->site)->create(['slug' => 'draft-only', 'title' => 'Черновик', 'sort_order' => 4]);

        $response = $this->visit('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', (string) $response->headers->get('Content-Type'));
        $xml = simplexml_load_string((string) $response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame(['http://dealer.localhost/', 'http://dealer.localhost/promo'], array_map('strval', $xml->xpath('//*[local-name()="loc"]') ?: []));
        $this->assertStringNotContainsString('draft-only', (string) $response->getContent());
        $this->assertStringNotContainsString('offers', (string) $response->getContent());
    }

    public function test_robots_points_to_the_sitemap_without_draft_or_application_urls(): void
    {
        $this->publish();

        $body = (string) $this->visit('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString("User-agent: *\n", $body);
        $this->assertStringContainsString('Sitemap: http://dealer.localhost/sitemap.xml', $body);
        $this->assertStringContainsString('Disallow: /_landflow/forms/', $body);

        foreach (['/sites/', 'preview', 'designer', '/dashboard', $this->site->public_id] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $body);
        }
    }

    public function test_unpublished_sites_have_no_sitemap_or_robots(): void
    {
        $this->visit('/sitemap.xml')->assertNotFound();
        $this->visit('/robots.txt')->assertNotFound();
    }

    public function test_static_files_never_shadow_published_robots_or_sitemap(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: *\nDisallow:", false)
            ->assertDontSee('Sitemap:');
    }

    public function test_draft_seo_changes_wait_for_the_next_publish(): void
    {
        $this->publish();
        $this->home->forceFill(['seo_title' => 'Новый заголовок'])->save();

        $this->visit('/')->assertSee('<title>Главная</title>', false)->assertDontSee('Новый заголовок');

        $this->publish();
        $this->visit('/')->assertSee('<title>Новый заголовок</title>', false);
    }

    public function test_preview_is_never_indexable(): void
    {
        $this->as($this->owner)->get(route('sites.preview', $this->site))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_owner_edits_all_seo_content_editor_only_title_and_description(): void
    {
        $this->saveSeo($this->owner, ['seo_title' => '  Заголовок  ', 'seo_description' => 'Описание', 'seo_noindex' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sites.designer', ['site' => $this->site, 'page' => $this->home->public_id]));
        $home = $this->home->fresh();
        $this->assertSame('Заголовок', $home?->seo_title);
        $this->assertSame('Описание', $home?->seo_description);
        $this->assertTrue($home?->seo_noindex);

        $editor = $this->member(WorkspaceRole::ContentEditor);
        $this->saveSeo($editor, ['seo_title' => '', 'seo_description' => 'От редактора', 'seo_noindex' => false])->assertSessionHasNoErrors();
        $home = $this->home->fresh();
        $this->assertNull($home?->seo_title);
        $this->assertSame('От редактора', $home?->seo_description);
        $this->assertTrue($home?->seo_noindex, 'Content Editor cannot change indexing.');

        $this->as($editor)->get(route('sites.designer', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.editSeo', true)
                ->where('can.editSeoIndexing', false)
                ->where('pages.0.seo.description', 'От редактора'));
    }

    public function test_designer_and_foreign_workspaces_cannot_edit_seo(): void
    {
        $this->saveSeo($this->member(WorkspaceRole::Designer), ['seo_title' => 'Чужой'])->assertForbidden();

        $stranger = User::factory()->create();
        $foreign = Workspace::factory()->create();
        $foreign->addMember($stranger, WorkspaceRole::Owner);
        $this->actingAs($stranger)->withSession([WorkspaceContext::SESSION_KEY => $foreign->public_id])
            ->patch(route('sites.pages.seo.update', ['site' => $this->site, 'page' => $this->home]), ['seo_title' => 'Чужой'])
            ->assertNotFound();

        $this->assertNull($this->home->fresh()?->seo_title);
    }

    public function test_seo_lengths_are_validated(): void
    {
        $this->saveSeo($this->owner, ['seo_title' => str_repeat('а', 121), 'seo_description' => str_repeat('б', 301)])
            ->assertSessionHasErrors(['seo_title', 'seo_description']);
    }

    private function offersPage(bool $noindex = false): Page
    {
        $page = Page::factory()->for($this->site)->create(['slug' => 'offers', 'title' => 'Предложения', 'sort_order' => 2]);
        $page->forceFill(['seo_noindex' => $noindex])->save();
        $this->place('cta', ['title' => 'Акция месяца'], page: $page);

        return $page;
    }

    private function publish(): void
    {
        $this->actAsMember($this->owner);
        $this->assertTrue(app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner)->succeeded());
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $path): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get('http://dealer.localhost'.$path));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return TestResponse<Response>
     */
    private function saveSeo(User $user, array $data): TestResponse
    {
        return $this->as($user)->patch(route('sites.pages.seo.update', ['site' => $this->site, 'page' => $this->home]), $data);
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
