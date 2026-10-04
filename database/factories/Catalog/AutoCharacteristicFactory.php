<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoCharacteristic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutoCharacteristic>
 */
class AutoCharacteristicFactory extends Factory
{
    protected $model = AutoCharacteristic::class;

    public function definition(): array
    {
        return [
            'code' => 'group_'.Str::lower(Str::random(8)),
            'name' => 'Группа',
            'unit' => null,
            'sort_order' => 0,
        ];
    }

    public function parameter(?AutoCharacteristic $group = null, ?string $unit = 'мм'): static
    {
        return $this->state(fn (): array => [
            'code' => 'param_'.Str::lower(Str::random(8)),
            'name' => 'Параметр',
            'parent_id' => ($group ?? AutoCharacteristic::factory()->create())->id,
            'unit' => $unit,
        ]);
    }
}
