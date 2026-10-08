<?php

namespace Tests\Feature\Blocks;

use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * D-122: the migration grants every Block Version already placed on a Site to that Site, once.
 */
class BlockVersionGrantBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_one_grant_per_site_and_placed_version(): void
    {
        $this->seed(OfficialBlockSeeder::class);
        $hero = BlockVersion::query()->whereHas('definition', fn ($query) => $query->where('slug', 'hero'))->firstOrFail();
        $cta = BlockVersion::query()->whereHas('definition', fn ($query) => $query->where('slug', 'cta'))->firstOrFail();
        $unused = BlockVersion::query()->whereHas('definition', fn ($query) => $query->where('slug', 'header'))->firstOrFail();

        $first = Site::factory()->create();
        $second = Site::factory()->create();
        $firstHome = Page::factory()->for($first)->home()->create();
        $firstAbout = Page::factory()->for($first)->create(['slug' => 'about', 'sort_order' => 1]);
        $secondHome = Page::factory()->for($second)->home()->create();
        foreach ([[$firstHome, $hero], [$firstHome, $hero], [$firstAbout, $hero], [$firstAbout, $cta], [$secondHome, $cta]] as $i => [$page, $version]) {
            BlockInstance::factory()->create(['page_id' => $page->id, 'block_version_id' => $version->id, 'sort_order' => $i, 'state_json' => []]);
        }

        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_16_000001_add_catalog_access_and_licenses.php');
        $migration->down();
        $migration->up();

        $grants = DB::table('site_block_version_grants')->get(['site_id', 'block_version_id', 'created_at']);
        $this->assertEqualsCanonicalizing(
            ["{$first->id}:{$hero->id}", "{$first->id}:{$cta->id}", "{$second->id}:{$cta->id}"],
            $grants->map(fn (object $row): string => "{$row->site_id}:{$row->block_version_id}")->all(),
        );
        $this->assertNotContains($unused->id, $grants->pluck('block_version_id')->all());
        $this->assertNotContains(null, $grants->pluck('created_at')->all());
    }
}
