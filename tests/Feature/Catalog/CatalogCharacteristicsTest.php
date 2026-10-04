<?php

namespace Tests\Feature\Catalog;

use App\Catalog\EquipmentCharacteristics;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoCharacteristicValue;
use App\Models\Catalog\AutoEquipment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

class CatalogCharacteristicsTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    public function test_tables_follow_catalog_v2_on_the_catalog_connection(): void
    {
        $this->assertSame(
            ['id', 'public_id', 'code', 'name', 'parent_id', 'unit', 'sort_order', 'created_at', 'updated_at'],
            Schema::connection('catalog')->getColumnListing('auto_characteristics'),
        );
        $this->assertSame(
            ['id', 'equipment_id', 'characteristic_id', 'value', 'created_at', 'updated_at'],
            Schema::connection('catalog')->getColumnListing('auto_characteristic_values'),
        );
        $this->assertFalse(Schema::hasTable('auto_characteristics'));
    }

    public function test_dictionary_is_exactly_two_levels(): void
    {
        $group = AutoCharacteristic::factory()->create(['code' => 'dimensions']);
        $length = AutoCharacteristic::factory()->parameter($group)->create(['code' => 'length']);

        $this->assertTrue($group->isGroup());
        $this->assertFalse($length->isGroup());

        $rejected = 0;

        foreach ([
            fn () => AutoCharacteristic::factory()->parameter($length)->create(),
            fn () => AutoCharacteristic::factory()->create(['unit' => 'мм']),
            fn () => AutoCharacteristic::factory()->create(['code' => 'Bad Code']),
            fn () => AutoCharacteristic::factory()->parameter($group)->create(['code' => 'engine_power']),
            fn () => $length->forceFill(['parent_id' => null])->save(),
        ] as $attempt) {
            try {
                $attempt();
            } catch (InvalidCatalogDataException) {
                $rejected++;
            }
        }

        $this->assertSame(5, $rejected);

        $this->expectException(QueryException::class);
        AutoCharacteristic::factory()->create(['code' => 'dimensions']);
    }

    public function test_values_belong_to_equipment_parameters_and_blank_means_no_row(): void
    {
        $equipment = AutoEquipment::factory()->create();
        $group = AutoCharacteristic::factory()->create(['code' => 'dimensions']);
        $length = AutoCharacteristic::factory()->parameter($group)->create(['code' => 'length']);
        $suspension = AutoCharacteristic::factory()->parameter($group, null)->create(['code' => 'front_suspension']);
        $sync = new EquipmentCharacteristics;

        $sync->sync($equipment, [$length->public_id => '4 999', $suspension->public_id => '  Независимая,   пружинная ']);

        $values = $equipment->characteristicValues()->with('characteristic')->get()->mapWithKeys(fn (AutoCharacteristicValue $value) => [$value->characteristic->code => $value->value]);
        $this->assertSame(['length' => '4999', 'front_suspension' => 'Независимая, пружинная'], $values->sortKeys()->reverse()->all());

        $sync->sync($equipment, [$length->public_id => '  ', $suspension->public_id => '1,5']);
        $this->assertSame(['front_suspension' => '1.5'], $equipment->characteristicValues()->with('characteristic')->get()->mapWithKeys(fn (AutoCharacteristicValue $value) => [$value->characteristic->code => $value->value])->all());

        $other = AutoEquipment::factory()->create();
        $this->assertSame(0, $other->characteristicValues()->count());

        $rejected = 0;

        foreach ([
            fn () => $sync->sync($equipment, [$group->public_id => '1']),
            fn () => $sync->sync($equipment, ['01hzzzzzzzzzzzzzzzzzzzzzzz' => '1']),
        ] as $attempt) {
            try {
                $attempt();
            } catch (InvalidCatalogDataException) {
                $rejected++;
            }
        }

        $this->assertSame(2, $rejected);

        $row = new AutoCharacteristicValue;
        $row->equipment_id = $equipment->id;
        $row->characteristic_id = $length->id;
        $row->value = '';
        $this->expectException(InvalidCatalogDataException::class);
        $row->save();
    }

    public function test_value_pair_is_unique_and_parameters_with_values_cannot_be_deleted(): void
    {
        $equipment = AutoEquipment::factory()->create();
        $length = AutoCharacteristic::factory()->parameter()->create();
        (new EquipmentCharacteristics)->sync($equipment, [$length->public_id => '4999']);

        $this->expectException(QueryException::class);
        $length->delete();
    }

    public function test_canonical_numbers(): void
    {
        $this->assertSame('1850000.5', EquipmentCharacteristics::canonical('1 850 000,5'));
        $this->assertSame('4999', EquipmentCharacteristics::canonical("4\u{00A0}999"));
        $this->assertSame('5 дверей', EquipmentCharacteristics::canonical('5  дверей'));
        $this->assertNull(EquipmentCharacteristics::canonical(null));
    }
}
