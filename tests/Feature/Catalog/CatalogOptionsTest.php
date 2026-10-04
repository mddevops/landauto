<?php

namespace Tests\Feature\Catalog;

use App\Catalog\EquipmentOptions;
use App\Enums\Catalog\OptionAvailability;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoOption;
use App\Models\Catalog\AutoOptionValue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class CatalogOptionsTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_tables_follow_catalog_v2(): void
    {
        $this->assertSame(
            ['id', 'public_id', 'code', 'name', 'parent_id', 'sort_order', 'created_at', 'updated_at'],
            Schema::connection('catalog')->getColumnListing('auto_options'),
        );
        $this->assertSame(
            ['id', 'option_id', 'equipment_id', 'is_base', 'created_at', 'updated_at'],
            Schema::connection('catalog')->getColumnListing('auto_option_values'),
        );
    }

    public function test_is_base_has_no_database_default(): void
    {
        $equipment = AutoEquipment::factory()->create();
        $abs = AutoOption::factory()->option()->create();

        $this->expectException(QueryException::class);
        DB::connection('catalog')->table('auto_option_values')->insert(['option_id' => $abs->id, 'equipment_id' => $equipment->id]);
    }

    public function test_option_values_are_explicit_and_missing_row_means_unknown(): void
    {
        $equipment = AutoEquipment::factory()->create();
        $safety = AutoOption::factory()->create(['code' => 'safety']);
        $abs = AutoOption::factory()->option($safety)->create(['code' => 'abs']);
        $heated = AutoOption::factory()->option($safety)->create(['code' => 'heated_front_seats']);
        $sync = new EquipmentOptions;

        $sync->sync($equipment, [$abs->public_id => OptionAvailability::Standard, $heated->public_id => OptionAvailability::Optional]);
        $this->assertSame(
            ['abs' => true, 'heated_front_seats' => false],
            $equipment->optionValues()->with('option')->get()->mapWithKeys(fn (AutoOptionValue $value) => [$value->option->code => $value->is_base])->sortKeys()->all(),
        );

        $sync->sync($equipment, [$heated->public_id => OptionAvailability::Unknown]);
        $this->assertSame(1, $equipment->optionValues()->count());
        $this->assertSame(0, AutoEquipment::factory()->create()->optionValues()->count(), 'options are not copied to other equipment');

        $rejected = 0;

        foreach ([
            fn () => $sync->sync($equipment, [$safety->public_id => OptionAvailability::Standard]),
            fn () => AutoOption::factory()->option($abs)->create(),
            function () use ($equipment, $abs): void {
                $row = new AutoOptionValue;
                $row->equipment_id = $equipment->id;
                $row->option_id = $abs->id;
                $row->save();
            },
        ] as $attempt) {
            try {
                $attempt();
            } catch (InvalidCatalogDataException) {
                $rejected++;
            }
        }

        $this->assertSame(3, $rejected);
    }

    public function test_availability_maps_to_is_base(): void
    {
        $this->assertSame(OptionAvailability::Unknown, OptionAvailability::fromIsBase(null));
        $this->assertSame(OptionAvailability::Optional, OptionAvailability::fromIsBase(false));
        $this->assertTrue(OptionAvailability::Standard->isBase());
        $this->assertNull(OptionAvailability::Unknown->isBase());
    }
}
