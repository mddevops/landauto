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
        return [
            'code' => 'group_'.Str::lower(Str::random(8)),
            'name' => 'Группа опций',
            'sort_order' => 0,
        ];
    }

    public function option(?AutoOption $group = null): static
    {
        return $this->state(fn (): array => [
            'code' => 'option_'.Str::lower(Str::random(8)),
            'name' => 'Опция',
            'parent_id' => ($group ?? AutoOption::factory()->create())->id,
        ]);
    }
}
