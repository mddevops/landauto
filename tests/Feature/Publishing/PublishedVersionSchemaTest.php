<?php

namespace Tests\Feature\Publishing;

use App\Enums\PublishedAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Models\PublishedAssetReference;
use App\Models\PublishedPage;
use App\Models\PublishedVersion;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PublishedVersionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_numbers_are_unique_per_site(): void
    {
        $site = Site::factory()->create();
        $other = Site::factory()->create();

        $first = PublishedVersion::factory()->for($site)->create();
        $second = PublishedVersion::factory()->for($site)->create();
        $foreign = PublishedVersion::factory()->for($other)->create();

        $this->assertSame(1, $first->version_number);
        $this->assertSame(2, $second->version_number);
        $this->assertSame(1, $foreign->version_number);
        $this->assertTrue(Str::isUlid($first->public_id));
        $this->assertSame(PublishedVersionStatus::Building, $first->fresh()?->status);

        $this->expectException(QueryException::class);
        PublishedVersion::factory()->for($site)->create(['version_number' => 2]);
    }

    public function test_snapshots_are_hidden_from_serialization(): void
    {
        $version = PublishedVersion::factory()->create();

        $array = $version->toArray();

        $this->assertArrayNotHasKey('draft_snapshot_json', $array);
        $this->assertArrayNotHasKey('public_manifest_json', $array);
        $this->assertArrayNotHasKey('id', $array);
        $this->assertArrayNotHasKey('site_id', $array);
    }

    public function test_ready_version_is_immutable(): void
    {
        $version = PublishedVersion::factory()->create();
        $this->addPage($version);

        $version->update(['status' => PublishedVersionStatus::Ready, 'ready_at' => now()]);

        try {
            $version->update(['status' => PublishedVersionStatus::Failed]);
            $this->fail('A ready version changed state.');
        } catch (LogicException) {
        }

        try {
            $this->addPage($version, 'about');
            $this->fail('An artifact was added to a ready version.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $version->delete();
    }

    public function test_snapshot_columns_cannot_change_while_building(): void
    {
        $version = PublishedVersion::factory()->create();

        $this->expectException(LogicException::class);
        $version->update(['public_manifest_json' => ['pages' => ['changed']]]);
    }

    public function test_artifacts_are_immutable_once_written(): void
    {
        $version = PublishedVersion::factory()->create();
        $page = $this->addPage($version);

        $this->expectException(LogicException::class);
        $page->update(['rendered_html' => '<p>changed</p>']);
    }

    public function test_slugs_are_unique_within_a_version(): void
    {
        $version = PublishedVersion::factory()->create();
        $this->addPage($version, 'home');

        $this->expectException(QueryException::class);
        $this->addPage($version, 'home');
    }

    public function test_asset_references_use_typed_kinds_and_ignore_failed_versions(): void
    {
        $ready = PublishedVersion::factory()->create();
        $failed = PublishedVersion::factory()->create();
        $assetId = (string) Str::ulid();
        $otherId = (string) Str::ulid();

        PublishedAssetReference::query()->create(['published_version_id' => $ready->id, 'kind' => PublishedAssetKind::SiteAsset, 'reference_public_id' => $assetId]);
        PublishedAssetReference::query()->create(['published_version_id' => $failed->id, 'kind' => PublishedAssetKind::SeriesMediaImage, 'reference_public_id' => $otherId]);

        $ready->update(['status' => PublishedVersionStatus::Ready, 'ready_at' => now()]);
        $failed->update(['status' => PublishedVersionStatus::Failed]);

        $this->assertTrue(PublishedAssetReference::isReferenced(PublishedAssetKind::SiteAsset, $assetId));
        $this->assertFalse(PublishedAssetReference::isReferenced(PublishedAssetKind::SeriesMediaImage, $assetId));
        $this->assertFalse(PublishedAssetReference::isReferenced(PublishedAssetKind::SeriesMediaImage, $otherId));
    }

    public function test_site_pointer_accepts_only_its_own_ready_version(): void
    {
        $site = Site::factory()->create();
        $building = PublishedVersion::factory()->for($site)->create();
        $foreign = PublishedVersion::factory()->create();
        $foreign->update(['status' => PublishedVersionStatus::Ready, 'ready_at' => now()]);

        foreach ([$building, $foreign] as $version) {
            try {
                $site->forceFill(['active_published_version_id' => $version->id])->save();
                $this->fail('The pointer accepted an invalid version.');
            } catch (LogicException) {
                $site->refresh();
            }
        }

        $building->update(['status' => PublishedVersionStatus::Ready, 'ready_at' => now()]);
        $site->forceFill(['active_published_version_id' => $building->id])->save();

        $this->assertTrue($site->fresh()?->activePublishedVersion?->is($building));
        $this->assertArrayNotHasKey('active_published_version_id', $site->toArray());
    }

    private function addPage(PublishedVersion $version, string $slug = 'home'): PublishedPage
    {
        return PublishedPage::query()->create([
            'published_version_id' => $version->id,
            'page_public_id' => (string) Str::ulid(),
            'slug' => $slug,
            'is_home' => $slug === 'home',
            'title' => 'Главная',
            'rendered_html' => '<h1>Главная</h1>',
            'hydration_json' => ['blocks' => []],
            'seo_json' => ['title' => 'Главная'],
            'content_hash' => hash('sha256', '<h1>Главная</h1>'),
        ]);
    }
}
