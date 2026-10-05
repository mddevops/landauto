<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublishedAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Models\Page;
use App\Models\PublishedVersion;
use App\Publishing\PublishedArtifactBuilder;
use App\Publishing\PublishedSnapshot;
use App\Publishing\PublishedSnapshotBuilder;
use App\Publishing\Rendering\PageRenderer;
use App\Publishing\Rendering\PageRenderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\Support\FakePageRenderer;
use Tests\TestCase;

class PublishedArtifactBuilderTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase;

    private FakePageRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
        $this->renderer = new FakePageRenderer;
        $this->app->instance(PageRenderer::class, $this->renderer);
    }

    public function test_every_page_is_rendered_from_the_manifest_with_version_scoped_urls(): void
    {
        $offers = Page::factory()->for($this->site)->create(['slug' => 'offers', 'title' => 'Предложения']);
        $this->place('cta', ['title' => 'Акция'], page: $offers);
        [$version, $snapshot] = $this->version();

        app(PublishedArtifactBuilder::class)->build($version, $snapshot->assetReferences);

        $this->assertCount(1, $this->renderer->calls);
        $pages = $version->pages()->get();
        $this->assertSame([$this->home->public_id, $offers->public_id], $pages->pluck('page_public_id')->all());
        $this->assertSame(['/', '/offers'], $pages->map->path()->all());
        $this->assertStringContainsString('Заголовок v1', $pages[0]->rendered_html);
        $this->assertSame(hash('sha256', $pages[0]->rendered_html), $pages[0]->content_hash);

        $payload = $pages[0]->hydration_json;
        $this->assertSame($version->public_id, $payload['version']);
        $this->assertSame("/_landflow/forms/{$version->public_id}", $payload['form_action']);
        $this->assertSame("/_landflow/media/{$version->public_id}/{$this->image->public_id}", $payload['vehicles'][0]['media']['sets'][0]['images'][0]['url']);
        $this->assertSame($this->form->public_id, $payload['popups'][0]['form']['public_id']);
        $this->assertSame([['public_id' => $this->home->public_id, 'path' => '/'], ['public_id' => $offers->public_id, 'path' => '/offers']], $payload['pages']);
        $this->assertArrayNotHasKey('offer_prices', $payload);
        $this->assertSame(['title' => 'Главная', 'description' => null, 'indexable' => true], $pages[0]->seo_json);

        $this->assertSame(
            [[PublishedAssetKind::SeriesMediaImage, $this->image->public_id]],
            $version->assetReferences()->get()->map(fn ($reference): array => [$reference->kind, $reference->reference_public_id])->all(),
        );
        $this->assertSame(PublishedVersionStatus::Building, $version->fresh()?->status);
    }

    public function test_a_render_failure_stores_nothing(): void
    {
        $this->renderer->fail = true;
        [$version, $snapshot] = $this->version();

        $this->expectException(PageRenderException::class);

        try {
            app(PublishedArtifactBuilder::class)->build($version, $snapshot->assetReferences);
        } finally {
            $this->assertSame(0, $version->pages()->count());
            $this->assertSame(0, $version->assetReferences()->count());
        }
    }

    public function test_an_empty_page_artifact_fails_the_whole_build(): void
    {
        $this->renderer->emptyPage = $this->home->public_id;
        [$version, $snapshot] = $this->version();

        $this->expectException(PageRenderException::class);

        try {
            app(PublishedArtifactBuilder::class)->build($version, $snapshot->assetReferences);
        } finally {
            $this->assertSame(0, $version->pages()->count());
        }
    }

    public function test_only_building_versions_get_artifacts(): void
    {
        [$version, $snapshot] = $this->version();
        $version->update(['status' => PublishedVersionStatus::Failed]);

        $this->expectException(LogicException::class);

        app(PublishedArtifactBuilder::class)->build($version, $snapshot->assetReferences);
    }

    /**
     * @return array{PublishedVersion, PublishedSnapshot}
     */
    private function version(): array
    {
        $snapshot = app(PublishedSnapshotBuilder::class)->build($this->site);
        $version = PublishedVersion::query()->create([
            'site_id' => $this->site->id,
            'version_number' => 1,
            'public_manifest_json' => $snapshot->publicManifest,
            'draft_snapshot_json' => $snapshot->draftSnapshot,
            'manifest_hash' => $snapshot->manifestHash,
        ]);

        return [$version, $snapshot];
    }
}
