<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoMark;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutoMark>
 */
class AutoMarkFactory extends Factory
{
    protected $model = AutoMark::class;

    public function definition(): array
    {
        $name = 'Марка '.Str::upper(Str::random(6));

        return [
            'name' => $name,
            'url' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }
}
