<?php

namespace Tests\Feature\Publishing;

use App\Enums\PlatformRole;
use App\Enums\PublishedVersionStatus;
use App\Enums\SubmissionMode;
use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\Page;
use App\Models\PlatformRoleAssignment;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\Submission;
use App\Models\User;
use App\Publishing\PublishOutcome;
use App\Publishing\PublishSite;
use App\Publishing\Rendering\PageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\Support\FakePageRenderer;
use Tests\TestCase;

class PublishedRuntimeTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    /** Draft and catalog tables a visitor request must never read (ADR-006 §6). */
    private const DRAFT_TABLES = ['pages', 'block_instances', 'site_vehicles', 'site_offers', 'site_offer_benefits', 'forms', 'form_fields', 'popups', 'site_assets'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
    }

    public function test_an_unpublished_or_unknown_site_is_a_safe_404(): void
    {
        $this->visit('/')->assertNotFound()->assertSee('Страница не найдена')->assertDontSee('Заголовок v1');
        $this->onPublicHost(fn () => $this->get('http://unknown.localhost/'))->assertNotFound();

        $this->onPublicHost(fn () => $this->get('http://www.localhost/'))->assertOk()->assertDontSee('Страница не найдена');
    }

    public function test_the_active_version_is_served_from_stored_html_without_draft_reads(): void
    {
        $this->publish();
        $this->editDraft('Заголовок v2', 230_000_000);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->visit('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<title>Главная</title>', false)
            ->assertSee('Заголовок v1')
            ->assertSee("2\u{A0}100\u{A0}000")
            ->assertDontSee('Заголовок v2')
            ->assertDontSee("2\u{A0}300\u{A0}000")
            ->assertSee('id="lf-page-data"', false)
            ->assertCookieMissing(config('session.cookie'));

        foreach ($queries as $sql) {
            foreach (self::DRAFT_TABLES as $table) {
                $this->assertDoesNotMatchRegularExpression('/\bfrom\s+"?'.$table.'"?\b/i', $sql, "Public request read Draft table {$table}.");
            }
        }

        $this->assertStringNotContainsString('offer_prices', (string) $response->getContent());
        $this->assertStringNotContainsString('draft_snapshot', (string) $response->getContent());
        $pageData = (string) $this->extractPageData($response);
        foreach (['"site_id"', '"workspace_id"', '"id":'] as $internalKey) {
            $this->assertStringNotContainsString($internalKey, $pageData);
        }
    }

    public function test_a_new_publish_switches_production_without_flushing_the_cache(): void
    {
        $this->publish();
        $this->visit('/')->assertSee('Заголовок v1');

        $this->editDraft('Заголовок v2', 230_000_000);
        $this->publish();

        $this->visit('/')->assertSee('Заголовок v2')->assertSee("2\u{A0}300\u{A0}000")->assertDontSee('Заголовок v1');
    }

    public function test_unknown_pages_application_routes_and_other_methods_are_404_on_public_hosts(): void
    {
        $offers = Page::factory()->for($this->site)->create(['slug' => 'offers', 'title' => 'Предложения']);
        $this->place('cta', ['title' => 'Акция месяца'], page: $offers);
        $this->publish();

        $this->visit('/offers')->assertOk()->assertSee('Акция месяца');
        $this->visit('/missing')->assertNotFound();
        $this->visit('/login')->assertNotFound();
        $this->visit('/dashboard')->assertNotFound();
        $this->visit('/sites/'.$this->site->public_id.'/designer')->assertNotFound();
        $this->onPublicHost(fn () => $this->post('http://dealer.localhost/login', ['email' => 'x']))->assertNotFound();
        $this->onPublicHost(fn () => $this->delete('http://dealer.localhost/'))->assertNotFound();
    }

    public function test_only_files_referenced_by_a_ready_version_are_delivered(): void
    {
        $draftAsset = $this->asset();
        $v1 = $this->publish()->version;
        $this->assertNotNull($v1);

        $this->visit("/_landflow/media/{$v1->public_id}/{$this->image->public_id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', $this->image->mime_type)
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->visit("/_landflow/assets/{$v1->public_id}/{$draftAsset->public_id}")->assertNotFound();

        $hero = $this->hero();
        $hero->forceFill(['state_json' => [...$hero->state_json, 'image' => $draftAsset->public_id]])->save();
        $v2 = $this->publish()->version;
        $this->assertNotNull($v2);

        $this->visit("/_landflow/assets/{$v2->public_id}/{$draftAsset->public_id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->visit("/_landflow/assets/{$v1->public_id}/{$draftAsset->public_id}")->assertNotFound();
        $this->visit("/_landflow/media/{$v1->public_id}/{$this->image->public_id}")->assertOk();

        $foreign = Site::factory()->create(['subdomain' => 'foreign']);
        $this->onPublicHost(fn () => $this->get("http://foreign.localhost/_landflow/media/{$v1->public_id}/{$this->image->public_id}"))->assertNotFound();
        $this->assertNull($foreign->active_published_version_id);
    }

    public function test_a_published_series_media_image_cannot_be_deleted(): void
    {
        $this->publish();
        $manager = User::factory()->create();
        PlatformRoleAssignment::query()->create(['user_id' => $manager->id, 'role' => PlatformRole::CatalogManager]);

        $this->actingAs($manager)->delete(route('platform.catalog.media.images.destroy', $this->image))->assertRedirect();

        $this->assertNotNull($this->image->fresh());
        Storage::disk('local')->assertExists($this->image->path);
    }

    public function test_public_leads_use_the_published_form_and_price_not_the_draft(): void
    {
        $v1 = $this->publish()->version;
        $this->assertNotNull($v1);
        $this->editDraft('Заголовок v2', 230_000_000);
        $this->form->fields()->where('key', 'name')->sole()->update(['label' => 'Имя v2']);

        $this->lead($v1, ['vehicle' => $this->vehicle->public_id, 'offer' => $this->offer->public_id, 'popup' => $this->popup->public_id, 'page' => $this->home->public_id, 'price' => 1, 'price_minor' => 1])
            ->assertCreated();

        $submission = Submission::query()->sole();
        $this->assertSame(SubmissionMode::Public, $submission->mode);
        $this->assertSame(210_000_000, $submission->context['trusted']['offer']['price_minor'] ?? null);
        $this->assertSame(['public_id' => $v1->public_id, 'version_number' => 1], $submission->context['trusted']['published_version'] ?? null);
        $this->assertSame('KIA Rio', $submission->context['trusted']['vehicle']['title'] ?? null);
        $this->assertSame('Имя', $submission->payload[0]['label']);
        $this->assertArrayNotHasKey('price', $submission->context['trusted'] ?? []);
    }

    public function test_an_older_ready_version_keeps_accepting_its_own_pages(): void
    {
        $v1 = $this->publish()->version;
        $this->editDraft('Заголовок v2', 230_000_000);
        $v2 = $this->publish()->version;
        $this->assertNotNull($v1);
        $this->assertNotNull($v2);

        $this->lead($v1, ['offer' => $this->offer->public_id])->assertCreated();
        $this->lead($v2, ['offer' => $this->offer->public_id], phone: '+7 (999) 222-33-44')->assertCreated();

        $this->assertSame([210_000_000, 230_000_000], Submission::query()->orderBy('id')->get()->map(fn (Submission $submission) => $submission->context['trusted']['offer']['price_minor'] ?? null)->all());
    }

    public function test_draft_only_failed_or_unpublished_forms_cannot_take_public_leads(): void
    {
        $v1 = $this->publish()->version;
        $this->assertNotNull($v1);

        $draftOnly = Form::factory()->for($this->site)->withLeadFields()->create();
        $this->onPublicHost(fn () => $this->postJson("http://dealer.localhost/_landflow/forms/{$v1->public_id}/{$draftOnly->public_id}", ['fields' => $this->fields()]))
            ->assertNotFound()->assertExactJson(['message' => 'Форма недоступна.']);

        $this->renderer()->fail = true;
        $this->editDraft('Заголовок v2', 230_000_000);
        $failed = $this->publish();
        $failedVersion = PublishedVersion::query()->where('status', PublishedVersionStatus::Failed->value)->sole();
        $this->assertFalse($failed->succeeded());
        $this->lead($failedVersion, [])->assertNotFound();

        $this->lead($v1, ['vehicle' => $this->site->public_id])->assertUnprocessable();
        $this->lead($v1, ['page' => Page::factory()->create()->public_id])->assertUnprocessable();

        $this->site->forceFill(['active_published_version_id' => null])->save();
        $this->lead($v1, [])->assertNotFound();
        $this->assertSame(0, Submission::query()->count());
    }

    private function publish(): PublishOutcome
    {
        $this->actAsMember($this->owner);

        return app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
    }

    /**
     * @return TestResponse<Response>
     */
    private function visit(string $path): TestResponse
    {
        return $this->onPublicHost(fn () => $this->get('http://dealer.localhost'.$path));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return TestResponse<Response>
     */
    private function lead(PublishedVersion $version, array $context, string $phone = '+7 (999) 111-22-33'): TestResponse
    {
        return $this->onPublicHost(fn () => $this->postJson(
            "http://dealer.localhost/_landflow/forms/{$version->public_id}/{$this->form->public_id}",
            ['fields' => $this->fields($phone), 'context' => $context],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function fields(string $phone = '+7 (999) 111-22-33'): array
    {
        return ['name' => 'Иван', 'phone' => $phone, 'consent' => true];
    }

    private function hero(): BlockInstance
    {
        return $this->home->blocks()->orderBy('sort_order')->firstOrFail();
    }

    private function editDraft(string $title, int $price): void
    {
        $hero = $this->hero();
        $hero->forceFill(['state_json' => [...$hero->state_json, 'title' => $title]])->save();
        $this->offer->forceFill(['price_minor' => $price])->save();
    }

    private function asset(): SiteAsset
    {
        $asset = new SiteAsset(['original_name' => 'hero.jpg']);
        $asset->public_id = $asset->newUniqueId();
        $asset->path = "site-assets/{$this->site->public_id}/{$asset->public_id}.jpg";
        $asset->mime_type = 'image/jpeg';
        $asset->size_bytes = 10;
        $asset->width = 10;
        $asset->height = 10;
        $asset->site()->associate($this->site)->save();
        Storage::disk(SiteAsset::DISK)->put($asset->path, 'jpeg-bytes');

        return $asset;
    }

    private function renderer(): FakePageRenderer
    {
        $renderer = app(PageRenderer::class);
        assert($renderer instanceof FakePageRenderer);

        return $renderer;
    }

    private function extractPageData(TestResponse $response): string
    {
        preg_match('#<script type="application/json" id="lf-page-data">(.*?)</script>#s', (string) $response->getContent(), $matches);

        return $matches[1] ?? '';
    }
}
