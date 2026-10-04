<?php

namespace Tests\Feature\Catalog;

use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaSet;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class SeriesMediaSetTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_media_sets_live_in_the_main_database_outside_the_catalog(): void
    {
        $this->assertTrue(Schema::hasTable('series_media_sets'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('series_media_sets'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('auto_colors'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('auto_paints'));
        $this->assertFalse(Schema::hasTable('auto_colors'));
    }

    public function test_set_references_an_existing_series_by_public_id(): void
    {
        $series = AutoSeries::factory()->create();
        $white = SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Белый', 'swatch_hex' => '#f5f5f5', 'sort_order' => 2]);
        SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Черный', 'swatch_hex' => null, 'sort_order' => 1]);

        $this->assertSame($series->public_id, $white->catalog_series_public_id);
        $this->assertArrayNotHasKey('id', $white->toArray());
        $this->assertSame(
            ['Черный', 'Белый'],
            SeriesMediaSet::query()->where('catalog_series_public_id', $series->public_id)->active()->ordered()->pluck('name')->all(),
        );

        $this->expectException(QueryException::class);
        SeriesMediaSet::factory()->forSeries($series)->create(['name' => 'Белый']);
    }

    public function test_invalid_series_swatch_and_series_move_are_rejected(): void
    {
        $set = SeriesMediaSet::factory()->create();
        $rejected = 0;

        foreach ([
            fn () => SeriesMediaSet::factory()->create(['catalog_series_public_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz']),
            fn () => SeriesMediaSet::factory()->create(['swatch_hex' => 'red']),
            fn () => SeriesMediaSet::factory()->create(['swatch_hex' => '#FFFFFF']),
            fn () => $set->forceFill(['catalog_series_public_id' => AutoSeries::factory()->create()->public_id])->save(),
        ] as $attempt) {
            try {
                $attempt();
            } catch (InvalidCatalogDataException) {
                $rejected++;
            }
        }

        $this->assertSame(4, $rejected);
    }
}
