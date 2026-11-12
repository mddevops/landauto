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
        return ['name' => 'Параметр '.Str::random(6), 'parent_id' => null, 'sort' => 0];
    }
}
