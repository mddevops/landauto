<?php

namespace Tests\Feature\Blocks;

use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Publishing\PublishOutcome;
use App\Publishing\PublishSite;
use App\Publishing\PublishValidator;
use App\Publishing\Rendering\PageRenderer;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\Support\FakePageRenderer;
use Tests\TestCase;

class SandboxedBlockRuntimeTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase, SubmitsPublishedForms;

    private const FIELDS = [
        ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок', 'default' => 'Промо', 'max_length' => 80],
        ['key' => 'photo', 'type' => 'image', 'label' => 'Фото'],
        ['key' => 'cta', 'type' => 'action', 'label' => 'Кнопка'],
    ];

    private const HTML = '<h2>{{ title }}</h2>{{#if photo}}<img src="{{ photo.url }}" alt="">{{/if}}<button type="button" data-landflow-action="cta">Подробнее</button>';

    private const JS = 'landflow.root.dataset.ready = "</script><b>yes</b>";';

    private BlockVersion $promo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->site->forceFill(['subdomain' => 'dealer'])->save();
        $definition = BlockDefinition::factory()->platform()->create(['slug' => 'promo-card', 'name' => 'Промо-карточка']);
        $this->promo = BlockVersion::factory()->sandboxed(self::HTML, 'h2 { color: #b91c1c; }', self::JS)
            ->for($definition, 'definition')
            ->create(['schema_json' => ['fields' => self::FIELDS]]);
    }

    public function test_platform_sandboxed_block_is_placed_from_the_library_with_its_published_source(): void
    {
        $developer = BlockDefinition::factory()->developer()->create(['slug' => 'dev-card']);
        BlockVersion::factory()->sandboxed()->for($developer, 'definition')->create();

        $this->as()->get(route('sites.designer', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('library', fn ($library): bool => collect($library)->contains('slug', 'promo-card') && ! collect($library)->contains('slug', 'dev-card')));

        $this->as()->post(route('sites.blocks.store', [$this->site, $this->home]), ['block' => 'promo-card'])->assertSessionHasNoErrors();
        $this->as()->post(route('sites.blocks.store', [$this->site, $this->home]), ['block' => 'dev-card'])
            ->assertSessionHasErrors(['block' => 'Этот блок недоступен.']);

        $block = BlockInstance::query()->latest('id')->firstOrFail();
        $this->assertSame($this->promo->id, $block->block_version_id);
        $this->assertSame('Промо', $block->state_json['title']);

        $source = ['name' => 'Промо-карточка', 'html' => self::HTML, 'css' => 'h2 { color: #b91c1c; }', 'js' => self::JS, 'fields' => self::FIELDS];
        $this->as()->get(route('sites.designer', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('blocks.0.sandbox', null)
                ->where('blocks.2.slug', 'promo-card')
                ->where('blocks.2.sandbox', $source)
                ->where('blocks.2.schema.fields', self::FIELDS));
        $this->as()->get(route('sites.preview', $this->site))
            ->assertInertia(fn (Assert $page) => $page
                ->where('blocks.0.sandbox', null)
                ->where('blocks.2.sandbox', $source));
    }

    public function test_only_platform_sandboxed_versions_can_be_placed(): void
    {
        $developer = BlockDefinition::factory()->developer()->create();
        $version = BlockVersion::factory()->sandboxed()->for($developer, 'definition')->create();

        $this->expectException(LogicException::class);
        BlockInstance::factory()->create(['page_id' => $this->home->id, 'block_version_id' => $version->id]);
    }

    public function test_publishing_snapshots_the_sandboxed_source_and_keeps_official_manifests_unchanged(): void
    {
        $officialOnly = $this->publish();
        $this->assertTrue($officialOnly->succeeded());
        $version = $officialOnly->version;
        $this->assertInstanceOf(PublishedVersion::class, $version);
        foreach ($version->public_manifest_json['pages'][0]['blocks'] as $entry) {
            $this->assertArrayNotHasKey('sandbox', $entry);
        }
        foreach ($version->pages()->sole()->hydration_json['blocks'] as $entry) {
            $this->assertArrayNotHasKey('sandbox', $entry);
        }

        $asset = SiteAsset::factory()->for($this->site)->create();
        Storage::disk('local')->put($asset->path, 'png-bytes');
        $this->placeSandboxed(['title' => 'Акция', 'photo' => $asset->public_id, 'cta' => ['type' => 'open_popup', 'popup' => $this->popup->public_id]]);
        $outcome = $this->publish();
        $this->assertTrue($outcome->succeeded(), implode(', ', $outcome->validation?->errorCodes() ?? [$outcome->publication->status->value]));
        $published = $outcome->version;
        $this->assertInstanceOf(PublishedVersion::class, $published);

        $entry = $published->public_manifest_json['pages'][0]['blocks'][2];
        $this->assertSame(['promo-card', '1.0.0'], [$entry['definition'], $entry['version']]);
        $this->assertSame(['name' => 'Промо-карточка', 'html' => self::HTML, 'css' => 'h2 { color: #b91c1c; }', 'js' => self::JS, 'fields' => self::FIELDS], $entry['sandbox']);

        $payload = $published->pages()->sole()->hydration_json;
        $this->assertSame($entry['sandbox'], $payload['blocks'][2]['sandbox']);
        $this->assertSame(['title' => 'Акция', 'photo' => $asset->public_id, 'cta' => ['type' => 'open_popup', 'popup' => $this->popup->public_id]], $payload['blocks'][2]['state']);
        $this->assertContains($asset->public_id, array_column($payload['assets'], 'public_id'));

        // Later catalog changes never reach the snapshot: versions are immutable and the source is copied.
        BlockVersion::query()->whereKey($this->promo->id)->toBase()->update(['js' => 'changed();']);
        $this->assertSame(self::JS, $published->fresh()?->public_manifest_json['pages'][0]['blocks'][2]['sandbox']['js']);
    }

    public function test_block_code_reaches_the_public_page_only_as_escaped_data(): void
    {
        $this->placeSandboxed(['title' => 'Акция']);
        $this->assertTrue($this->publish()->succeeded());

        $content = (string) $this->onPublicHost(fn () => $this->get('http://dealer.localhost/'))->assertOk()->getContent();
        $this->assertStringNotContainsString('</script><b>yes</b>', $content);
        $this->assertStringNotContainsString('<h2>{{ title }}</h2>', $content);
        $this->assertStringContainsString('\u003C/script\u003E\u003Cb\u003Eyes', $content);
    }

    public function test_publish_validation_rejects_invalid_state_and_sources_that_fail_the_checks(): void
    {
        $block = $this->placeSandboxed(['title' => 'Акция']);
        $this->assertNotContains('block_state_invalid', $this->issueCodes());

        // Stored state is validated again against the published schema.
        BlockInstance::query()->whereKey($block->id)->toBase()->update(['state_json' => json_encode(['title' => str_repeat('я', 81)])]);
        $this->assertContains('block_state_invalid', $this->issueCodes());
        BlockInstance::query()->whereKey($block->id)->toBase()->update(['state_json' => json_encode(['title' => 'Акция'])]);

        // A version that slipped past today's checks (e.g. created before a rule existed).
        BlockVersion::query()->whereKey($this->promo->id)->toBase()->update(['html' => '<h2>{{ title }}</h2><iframe></iframe>']);
        $this->assertContains('block_source_rejected', $this->issueCodes());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function placeSandboxed(array $state): BlockInstance
    {
        return BlockInstance::factory()->create([
            'page_id' => $this->home->id,
            'block_version_id' => $this->promo->id,
            'sort_order' => $this->home->blocks()->count(),
            'state_json' => $state,
        ]);
    }

    /**
     * @return list<string>
     */
    private function issueCodes(): array
    {
        return app(PublishValidator::class)->validate(Site::query()->findOrFail($this->site->id))->errorCodes();
    }

    private function publish(): PublishOutcome
    {
        $this->app->instance(PageRenderer::class, new FakePageRenderer);
        $this->actAsMember($this->owner);

        return app(PublishSite::class)->handle(Site::query()->findOrFail($this->site->id), $this->owner);
    }

    private function as(): static
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
