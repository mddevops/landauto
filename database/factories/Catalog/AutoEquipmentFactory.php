<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoEquipment>
 */
class AutoEquipmentFactory extends Factory
{
    protected $model = AutoEquipment::class;

    public function definition(): array
    {
        return [
            'modification_id' => AutoModification::factory(),
            'name' => 'Comfort',
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }
}
