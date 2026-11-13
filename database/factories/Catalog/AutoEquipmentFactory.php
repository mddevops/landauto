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
            'series_id' => fn (array $attributes): int => (int) AutoModification::query()->whereKey($attributes['modification_id'])->value('series_id'),
            'external_id' => fake()->unique()->numberBetween(50_000_000, 59_999_999),
            'external_key' => function (array $attributes): string {
                $modification = AutoModification::query()->with('series')->whereKey($attributes['modification_id'])->firstOrFail();

                return "{$modification->series->external_id}_{$attributes['external_id']}_{$modification->external_id}";
            },
            'name' => 'Comfort',
            'status' => 1,
        ];
    }

    /**
     * The modification row without a named trim.
     */
    public function base(): static
    {
        return $this->state(['external_id' => 0, 'name' => '']);
    }
}
