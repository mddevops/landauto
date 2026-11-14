<?php

namespace Tests\Feature\Publishing;

use App\Automotive\SectionPages;
use App\Automotive\VehiclePages;
use App\Enums\SiteType;
use App\Models\Page;
use App\Models\PublishedPage;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteSection;
use App\Models\Submission;
use App\Publishing\PublishSite;
use App\Publishing\RestoreVersion;
use App\Publishing\SectionManifest;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class PublishedSectionsTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private const ITEM = 'catalog/kia/rio-iv';

    private SiteSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['site_type' => SiteType::MultiPage])->save();
        $this->withPublicNumber($this->site);
        $this->section = SiteSection::factory()->for($this->site)->create(['title' => 'Каталог']);
        app(SectionPages::class)->ensure($this->section);
    }

    public function test_published_site_serves_every_section_address_from_the_snapshot(): void
    {
        $version = $this->publish();

        $this->assertSame(
            ['', 'catalog', 'catalog/kia', self::ITEM],
            PublishedPage::query()->where('published_version_id', $version->id)->orderBy('sort_order')->pluck('slug')->map(fn (string $slug): string => $slug === $this->home->slug ? '' : $slug)->all(),
        );

        $this->visit('/'.self::ITEM)
            ->assertOk()
            ->assertSee('<title>KIA Rio IV Седан</title>', false)
            ->assertSee('<link rel="canonical" href="http://localhost/100000000001/'.self::ITEM.'">', false);
        $this->visit('/catalog/kia')->assertOk()->assertSee('<title>KIA — Каталог</title>', false);
        $this->visit('/catalog')->assertOk()->assertSee('<title>Каталог</title>', false);

        foreach (['/catalog/bmw', '/catalog/kia/unknown', '/_catalog-item', '/catalog/kia/rio-iv/extra', '/catalog/kia/rio', '/catalog/kia/rio/iv-sedan'] as $missing) {
            $this->visit($missing)->assertNotFound();
        }

        $payload = $this->payload($version, self::ITEM);
        $this->assertSame(['role' => 'item', 'mark' => 'kia', 'vehicle' => $this->vehicle->public_id], $payload['page_context']);
        $this->assertSame('catalog', $payload['catalog']['prefix']);
        $this->assertSame('rio-iv', $payload['catalog']['vehicles'][0]['slug']);
        $this->assertArrayHasKey('catalog', $this->payload($version, ''), 'vehicle cards on ordinary Pages link to section pages');
        $this->assertArrayNotHasKey('page_context', $this->payload($version, ''));

        $urls = $this->sitemap();
        $this->assertContains('http://localhost/100000000001/'.self::ITEM, $urls);
        $this->assertContains('http://localhost/100000000001/catalog/kia', $urls);
    }

    public function test_draft_changes_reach_the_public_site_only_after_publish(): void
    {
        $this->publish();
        $this->section->forceFill(['enabled' => false])->save();
        $this->visit('/'.self::ITEM)->assertOk();

        $this->publish();
        $this->visit('/'.self::ITEM)->assertNotFound();
        $this->visit('/catalog')->assertNotFound();
        $this->assertNotContains('http://localhost/100000000001/catalog', $this->sitemap());
    }

    public function test_previous_prefixes_and_vehicle_addresses_redirect_after_publish(): void
    {
        $this->as()->patch(route('sites.sections.update', [$this->site, $this->section]), ['title' => 'Каталог', 'prefix' => 'auto', 'enabled' => true])->assertSessionHasNoErrors();
        app(VehiclePages::class)->ensureSlugs($this->site);
        app(VehiclePages::class)->rename($this->vehicle->fresh() ?? $this->vehicle, 'rio-sedan');
        $this->publish();

        $this->visit('/auto/kia/rio-sedan')->assertOk();
        $this->visit('/catalog/kia?utm_source=ya')->assertStatus(301)->assertHeader('Location', 'http://localhost/100000000001/auto/kia?utm_source=ya');
        $this->visit('/auto/kia/rio-iv')->assertStatus(301)->assertHeader('Location', 'http://localhost/100000000001/auto/kia/rio-sedan');
        $this->visit('/catalog/kia/rio-iv')->assertStatus(301)->assertHeader('Location', 'http://localhost/100000000001/auto/kia/rio-sedan');
        $this->visit('/catalog/kia/bmw')->assertNotFound();
    }

    public function test_leads_from_generated_pages_keep_their_page_and_vehicle(): void
    {
        $template = $this->section->pages()->where('section_role', 'item')->sole();

        $this->submitPublished($this->form, [
            'fields' => ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'consent' => true],
            'context' => ['page' => SectionManifest::pageId($template->public_id, self::ITEM), 'popup' => $this->popup->public_id, 'vehicle' => $this->vehicle->public_id],
        ])->assertCreated();

        $trusted = Submission::query()->sole()->context['trusted'] ?? [];
        $this->assertSame('KIA Rio IV Седан', $trusted['page']['title'] ?? null);
        $this->assertSame($this->vehicle->public_id, $trusted['vehicle']['public_id'] ?? null);
    }

    public function test_restore_brings_back_sections_template_pages_and_vehicle_addresses(): void
    {
        $item = $this->section->pages()->where('section_role', 'item')->sole();
        $item->forceFill(['seo_noindex' => true])->save();
        $blocks = $item->blocks()->count();
        $version = $this->publish();

        $this->as()->patch(route('sites.sections.update', [$this->site, $this->section]), ['title' => 'Авто', 'prefix' => 'auto', 'enabled' => false])->assertSessionHasNoErrors();
        $item->forceFill(['title' => 'Изменено', 'seo_noindex' => false])->save();
        $item->blocks()->latest('sort_order')->firstOrFail()->delete();
        app(VehiclePages::class)->rename($this->vehicle->fresh() ?? $this->vehicle, 'rio-sedan');

        app(RestoreVersion::class)->handle($this->site, $version, $this->owner);

        $section = $this->section->fresh();
        $this->assertNotNull($section);
        $this->assertSame(['Каталог', 'catalog', true], [$section->title, $section->prefix, $section->enabled]);
        $this->assertSame([], $section->redirects()->pluck('prefix')->all());
        $this->assertTrue($item->fresh()?->seo_noindex);
        $this->assertSame($blocks, $item->blocks()->count());
        $this->assertSame('rio-iv', $this->vehicle->fresh()?->slug);
        $this->assertSame(['rio-sedan'], $this->vehicle->slugRedirects()->pluck('slug')->all());

        $section->pages()->get()->each(function (Page $page): void {
            $page->blocks()->delete();
            $page->delete();
        });
        $section->delete();
        app(RestoreVersion::class)->handle($this->site, $version, $this->owner);

        $restored = $this->site->sections()->sole();
        $this->assertSame($this->section->public_id, $restored->public_id);
        $this->assertSame(3, $restored->pages()->count());
        $this->assertSame($blocks, $restored->pages()->where('section_role', 'item')->sole()->blocks()->count());
    }

    public function test_restore_skips_model_pages_of_versions_before_d131(): void
    {
        $version = $this->publish();
        $snapshot = $version->draft_snapshot_json;
        $model = $snapshot['sections'][0]['pages'][0];
        $snapshot['sections'][0]['pages'][] = [...$model, 'public_id' => strtolower((string) Str::ulid()), 'role' => 'model', 'slug' => '_catalog-model', 'blocks' => []];
        PublishedVersion::query()->whereKey($version->id)->update(['draft_snapshot_json' => json_encode($snapshot)]);

        app(RestoreVersion::class)->handle($this->site, $version->fresh() ?? $version, $this->owner);

        $this->assertEqualsCanonicalizing(['index', 'mark', 'item'], $this->section->pages()->pluck('section_role')->map(fn ($role): string => $role->value)->all());
    }

    private function publish(): PublishedVersion
    {
        $this->actAsMember($this->owner);
        $result = app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
        $this->assertTrue($result->succeeded());

        return PublishedVersion::query()->findOrFail(Site::query()->findOrFail($this->site->id)->active_published_version_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PublishedVersion $version, string $slug): array
    {
        $page = PublishedPage::query()->where('published_version_id', $version->id)
            ->when($slug === '', fn ($query) => $query->where('is_home', true), fn ($query) => $query->where('slug', $slug))
            ->sole();

        return $page->hydration_json;
    }

    /**
     * @return list<string>
     */
    private function sitemap(): array
    {
        $xml = simplexml_load_string((string) $this->visit('/sitemap.xml')->assertOk()->getContent());
        $this->assertNotFalse($xml);

        return array_map('strval', $xml->xpath('//*[local-name()="loc"]') ?: []);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $path): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get('http://localhost/100000000001'.$path));
    }

    private function as(): self
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
