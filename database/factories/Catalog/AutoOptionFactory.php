<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoOption;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutoOption>
 */
class AutoOptionFactory extends Factory
{
    protected $model = AutoOption::class;

    public function definition(): array
    {
        return ['name' => 'Опция '.Str::random(6), 'parent_id' => null, 'sort' => 0];
    }
}
