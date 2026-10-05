<?php

namespace Tests\Feature\Publishing;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Enums\PublishedAssetKind;
use App\Models\BlacklistEntry;
use App\Models\SiteAsset;
use App\Models\Submission;
use App\Publishing\PublishedSnapshotBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPublishableSite;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class PublishedSnapshotBuilderTest extends TestCase
{
    use BuildsPublishableSite, RefreshCatalogDatabase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildPublishableSite();
    }

    public function test_manifest_holds_visible_blocks_with_pinned_versions_and_exact_prices(): void
    {
        $this->place('cta', ['title' => 'Скрытый блок'], hidden: true);

        $manifest = $this->builder()->build($this->site)->publicManifest;

        $this->assertSame(1, $manifest['schema']);
        $this->assertSame(['public_id' => $this->site->public_id, 'name' => 'Дилер'], $manifest['site']);
        $this->assertTrue($manifest['branding']);
        $this->assertCount(1, $manifest['pages']);
        $page = $manifest['pages'][0];
        $this->assertSame([$this->home->public_id, 'home', true], [$page['public_id'], $page['slug'], $page['is_home']]);
        $this->assertSame(['hero', 'vehicle-card'], array_column($page['blocks'], 'definition'));
        $this->assertSame($this->home->blocks()->firstOrFail()->version->version, $page['blocks'][0]['version']);
        $this->assertSame('Заголовок v1', $page['blocks'][0]['state']['title']);

        $vehicle = $manifest['vehicles'][0];
        $this->assertSame('KIA', $vehicle['mark']);
        $this->assertSame('2 100 000 ₽', str_replace("\u{a0}", ' ', $vehicle['offers'][0]['price_label']));
        $this->assertSame(['price_minor' => 210_000_000, 'currency' => 'RUB'], $manifest['offer_prices'][$this->offer->public_id]);
        $this->assertSame($this->image->public_id, $vehicle['media']['sets'][0]['images'][0]['public_id']);
        $this->assertArrayNotHasKey('url', $vehicle['media']['sets'][0]['images'][0]);

        $this->assertSame($this->form->public_id, $manifest['popups'][0]['form']);
        $this->assertSame(['name', 'phone', 'consent'], array_column($manifest['forms'][0]['fields'], 'key'));
    }

    public function test_same_draft_gives_the_same_hash_and_a_change_gives_a_new_one(): void
    {
        $first = $this->builder()->build($this->site);
        $second = $this->builder()->build($this->site);

        $this->assertSame($first->manifestHash, $second->manifestHash);
        $this->assertSame($first->publicManifest, $second->publicManifest);

        $this->offer->update(['price_minor' => 230_000_000]);

        $this->assertNotSame($first->manifestHash, $this->builder()->build($this->site)->manifestHash);
    }

    public function test_snapshots_contain_no_secrets_operational_data_or_numeric_ids(): void
    {
        config(['services.yandex_smartcaptcha.server_key' => 'server-secret-value']);
        $this->site->forceFill(['form_security' => ['captcha_required' => true]])->save();
        Submission::factory()->for($this->form)->create(['phone_normalized' => '79990001122']);
        (new BlacklistEntry)->forceFill(['scope' => BlacklistScope::Site, 'site_id' => $this->site->id, 'type' => BlacklistType::Phone, 'value' => '79995554433'])->save();

        $snapshot = $this->builder()->build($this->site);
        $json = json_encode([$snapshot->publicManifest, $snapshot->draftSnapshot], JSON_UNESCAPED_UNICODE) ?: '';

        $this->assertTrue($snapshot->publicManifest['captcha_required']);
        foreach (['server-secret-value', '79990001122', '79995554433', 'form_security', '"site_id"', '"form_id"', '"workspace_id"', '/sites/'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_draft_snapshot_keeps_editable_state_including_hidden_and_inactive_entities(): void
    {
        $hidden = $this->place('cta', ['title' => 'Скрытый блок'], hidden: true);
        $this->popup->update(['status' => false]);

        $draft = $this->builder()->build($this->site)->draftSnapshot;

        $blocks = $draft['pages'][0]['blocks'];
        $this->assertSame($hidden->public_id, $blocks[2]['public_id']);
        $this->assertTrue($blocks[2]['is_hidden']);
        $this->assertFalse($draft['popups'][0]['status']);
        $this->assertSame($this->form->public_id, $draft['popups'][0]['form']);
        $this->assertSame(210_000_000, $draft['vehicles'][0]['offers'][0]['price_minor']);
        $this->assertSame([], $draft['vehicles'][0]['media_sets']);
        $this->assertSame($this->vehicle->catalog_series_public_id, $draft['vehicles'][0]['catalog_series_public_id']);
        $this->assertSame('phone', $draft['forms'][0]['fields'][1]['key']);
    }

    public function test_asset_references_cover_rendered_site_assets_and_media_images_only(): void
    {
        $shown = SiteAsset::factory()->for($this->site)->create();
        $draftOnly = SiteAsset::factory()->for($this->site)->create();
        $this->place('hero', ['image' => $shown->public_id]);
        $this->place('hero', ['image' => $draftOnly->public_id], hidden: true);

        $references = $this->builder()->build($this->site)->assetReferences;

        $this->assertEqualsCanonicalizing([
            ['kind' => PublishedAssetKind::SiteAsset, 'public_id' => $shown->public_id],
            ['kind' => PublishedAssetKind::SeriesMediaImage, 'public_id' => $this->image->public_id],
        ], $references);
    }

    private function builder(): PublishedSnapshotBuilder
    {
        return app(PublishedSnapshotBuilder::class);
    }
}
