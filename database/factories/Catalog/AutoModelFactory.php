<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AutoModel>
 */
class AutoModelFactory extends Factory
{
    protected $model = AutoModel::class;

    public function definition(): array
    {
        return [
            'mark_id' => AutoMark::factory(),
            'name' => 'Модель '.Str::upper(Str::random(4)),
            'url' => 'model-'.Str::lower(Str::random(8)),
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }
}
