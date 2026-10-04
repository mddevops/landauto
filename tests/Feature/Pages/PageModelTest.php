<?php

namespace Tests\Feature\Pages;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_belongs_to_site_and_serializes_without_internal_keys(): void
    {
        $site = Site::factory()->create();
        $page = Page::factory()->for($site)->create(['title' => 'О компании', 'slug' => 'o-kompanii']);

        $this->assertTrue($page->site->is($site));
        $this->assertTrue($site->pages()->sole()->is($page));
        $this->assertTrue(Str::isUlid($page->public_id));
        $this->assertSame('public_id', $page->getRouteKeyName());
        $this->assertFalse($page->is_home);
        $this->assertArrayNotHasKey('id', $page->toArray());
        $this->assertArrayNotHasKey('site_id', $page->toArray());
    }

    public function test_slug_is_unique_per_site_only(): void
    {
        $site = Site::factory()->create();
        Page::factory()->for($site)->create(['slug' => 'kontakty']);
        Page::factory()->for(Site::factory())->create(['slug' => 'kontakty']);

        $this->expectException(QueryException::class);

        Page::factory()->for($site)->create(['slug' => 'kontakty']);
    }

    public function test_site_has_at_most_one_home_page(): void
    {
        $site = Site::factory()->create();
        $home = Page::factory()->for($site)->home()->create();
        Page::factory()->for($site)->count(2)->create();
        Page::factory()->for(Site::factory())->home()->create();

        $this->assertTrue($site->homePage()->sole()->is($home));
        $this->assertTrue($home->refresh()->is_home);

        $this->expectException(QueryException::class);

        Page::factory()->for($site)->create(['slug' => 'vtoraya-glavnaya', 'is_home' => true]);
    }

    public function test_page_site_and_public_id_are_immutable(): void
    {
        $page = Page::factory()->create();
        $originalSiteId = $page->site_id;

        try {
            $page->forceFill(['site_id' => Site::factory()->create()->id])->save();
            $this->fail('Page site must be immutable.');
        } catch (LogicException) {
            $this->assertSame($originalSiteId, $page->fresh()?->site_id);
        }

        $this->expectException(LogicException::class);

        $page->refresh()->forceFill(['public_id' => (string) Str::ulid()])->save();
    }

    public function test_migration_backfills_one_home_page_for_existing_sites(): void
    {
        $sites = Site::factory()->count(2)->create();
        Schema::drop('pages');

        $migration = require database_path('migrations/2026_10_03_000001_create_pages_table.php');
        $migration->up();

        foreach ($sites as $site) {
            $home = $site->pages()->sole();
            $this->assertTrue($home->is_home);
            $this->assertSame(Page::HOME_TITLE, $home->title);
            $this->assertSame(Page::HOME_SLUG, $home->slug);
            $this->assertTrue(Str::isUlid($home->public_id));
        }
    }
}
