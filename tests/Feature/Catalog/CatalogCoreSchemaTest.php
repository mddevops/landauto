<?php

namespace Tests\Feature\Catalog;

use App\Catalog\CatalogDatabase;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class CatalogCoreSchemaTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private const CORE_TABLES = ['auto_marks', 'auto_models', 'auto_generations', 'auto_series', 'auto_modifications', 'auto_equipments'];

    public function test_core_tables_live_only_on_the_catalog_connection(): void
    {
        foreach (self::CORE_TABLES as $table) {
            $this->assertTrue(Schema::connection('catalog')->hasTable($table), $table);
            $this->assertFalse(Schema::connection(config('database.default'))->hasTable($table), "{$table} leaked into the main database");
        }

        $this->assertSame('catalog', (new AutoMark)->getConnectionName());
        $this->assertSame('catalog', (new AutoEquipment)->getConnectionName());
    }

    public function test_core_columns_follow_catalog_v2(): void
    {
        $this->assertSame(
            ['id', 'public_id', 'name', 'name_ru', 'url', 'logo_min', 'logo_big', 'country', 'status', 'sort_order', 'created_at', 'updated_at'],
            Schema::connection('catalog')->getColumnListing('auto_marks'),
        );
        $this->assertSame(
            ['id', 'public_id', 'modification_id', 'name', 'status', 'sort_order', 'created_at', 'updated_at'],
            Schema::connection('catalog')->getColumnListing('auto_equipments'),
        );
        $this->assertSame(
            ['id', 'public_id', 'series_id', 'name', 'engine_volume', 'engine_power', 'engine', 'transmission', 'drive', 'consumption_100_km', 'acceleration_0_100', 'status', 'sort_order', 'created_at', 'updated_at'],
            Schema::connection('catalog')->getColumnListing('auto_modifications'),
        );
        $this->assertFalse(Schema::connection('catalog')->hasColumn('auto_series', 'model_id'));
        $this->assertFalse(Schema::connection('catalog')->hasColumn('auto_equipments', 'series_id'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('auto_configurations'));
        $this->assertFalse(Schema::connection('catalog')->hasTable('auto_colors'));
    }

    public function test_full_chain_with_immutable_public_ids_and_hidden_numeric_ids(): void
    {
        $equipment = AutoEquipment::factory()->create();
        $series = $equipment->modification->series;

        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $equipment->public_id);
        $this->assertSame('public_id', $equipment->getRouteKeyName());
        $this->assertArrayNotHasKey('id', $equipment->toArray());
        $this->assertArrayNotHasKey('modification_id', $equipment->toArray());
        $this->assertTrue($series->generation->model->mark->exists);

        $this->expectException(LogicException::class);
        $series->public_id = strtolower((string) str()->ulid());
        $series->save();
    }

    public function test_hierarchy_parent_is_fixed_after_creation(): void
    {
        $generation = AutoGeneration::factory()->create();
        $generation->model_id = AutoModel::factory()->create()->id;

        $this->expectException(InvalidCatalogDataException::class);
        $generation->save();
    }

    public function test_deleting_a_parent_with_children_is_restricted(): void
    {
        $model = AutoModel::factory()->create();

        $this->expectException(QueryException::class);
        $model->mark->delete();
    }

    public function test_url_is_unique_per_parent(): void
    {
        $mark = AutoMark::factory()->create();
        AutoModel::factory()->for($mark, 'mark')->create(['url' => 'rio']);
        AutoModel::factory()->create(['url' => 'rio']);

        $this->expectException(QueryException::class);
        AutoModel::factory()->for($mark, 'mark')->create(['url' => 'rio']);
    }

    public function test_year_to_cannot_precede_year_from(): void
    {
        $this->expectException(InvalidCatalogDataException::class);
        AutoGeneration::factory()->create(['year_from' => 2020, 'year_to' => 2019]);
    }

    public function test_model_grouping_parent_must_share_the_mark_and_have_no_cycles(): void
    {
        $parent = AutoModel::factory()->create();
        $child = AutoModel::factory()->for($parent->mark, 'mark')->create(['parent_id' => $parent->id]);
        $this->assertSame($parent->id, $child->parent?->id);

        $rejected = 0;

        foreach ([
            fn () => AutoModel::factory()->create(['parent_id' => $parent->id]),
            fn () => $parent->forceFill(['parent_id' => $parent->id])->save(),
            fn () => $parent->forceFill(['parent_id' => $child->id])->save(),
        ] as $attempt) {
            try {
                $attempt();
            } catch (InvalidCatalogDataException) {
                $rejected++;
            }
        }

        $this->assertSame(3, $rejected);
    }

    #[DataProvider('disabledLevels')]
    public function test_a_disabled_ancestor_hides_the_whole_branch(string $level): void
    {
        $equipment = AutoEquipment::factory()->create();
        $modification = $equipment->modification;
        $series = $modification->series;
        $generation = $series->generation;
        $model = $generation->model;

        $this->assertTrue(AutoEquipment::query()->available()->whereKey($equipment->id)->exists());

        $target = ['mark' => $model->mark, 'model' => $model, 'generation' => $generation, 'series' => $series, 'modification' => $modification][$level];
        $target->update(['status' => false]);

        $this->assertFalse(AutoEquipment::query()->available()->whereKey($equipment->id)->exists());
        $this->assertSame(
            $level === 'modification',
            AutoSeries::query()->available()->whereKey($series->id)->exists(),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function disabledLevels(): array
    {
        return array_combine(
            ['mark', 'model', 'generation', 'series', 'modification'],
            [['mark'], ['model'], ['generation'], ['series'], ['modification']],
        );
    }

    public function test_decimal_values_are_never_floats(): void
    {
        $modification = AutoModification::factory()->create(['engine_power' => '367.5', 'consumption_100_km' => '7.25']);

        $this->assertSame('367.50', $modification->fresh()?->engine_power);
        $this->assertSame('7.250', $modification->fresh()?->consumption_100_km);
    }

    public function test_catalog_connection_must_not_target_the_main_database(): void
    {
        $guard = new CatalogDatabase;

        // Only connection config arrays change; the already-open default connection is untouched.
        config([
            'database.connections.sqlite' => ['driver' => 'mysql', 'host' => 'MySQL-8.0', 'port' => '3306', 'database' => 'landauto'],
            'database.connections.catalog' => ['driver' => 'mysql', 'host' => 'mysql-8.0', 'port' => '3306', 'database' => 'landflow_catalog'],
        ]);
        $guard->assertSeparateFromMain();

        config(['database.connections.catalog.database' => 'LANDAUTO']);
        $this->expectException(RuntimeException::class);
        $guard->assertSeparateFromMain();
    }

    public function test_catalog_migrate_command_refuses_the_main_database(): void
    {
        $file = storage_path('framework/testing/catalog-guard.sqlite');
        config([
            'database.connections.sqlite.database' => $file,
            'database.connections.catalog.database' => $file,
        ]);

        $this->artisan('catalog:migrate', ['--force' => true])->assertFailed();
        $this->assertFileDoesNotExist($file);
    }
}
