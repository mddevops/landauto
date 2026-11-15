<?php

namespace Database\Factories;

use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockVehicleStatus;
use App\Models\Catalog\AutoSeries;
use App\Models\StockVehicle;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a migrated catalog connection.
 *
 * @extends Factory<StockVehicle>
 */
class StockVehicleFactory extends Factory
{
    protected $model = StockVehicle::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'condition' => StockCondition::New,
            'catalog_series_ref' => fn (): string => (string) AutoSeries::factory()->create()->ref(),
            'year' => (int) date('Y'),
            'body_name' => 'Седан',
            'color_name' => 'Белый',
            'color_swatch_hex' => '#FFFFFF',
            'mileage_km' => null,
            'availability' => StockAvailability::InStock,
            'price_minor' => 1_500_000_00,
            'currency' => Money::DEFAULT_CURRENCY,
            'status' => StockVehicleStatus::Draft,
        ];
    }

    public function forSeries(AutoSeries $series): static
    {
        return $this->state(['catalog_series_ref' => (string) $series->ref()]);
    }

    public function used(int $mileageKm = 50_000, int $ownersCount = 1): static
    {
        return $this->state([
            'condition' => StockCondition::Used,
            'mileage_km' => $mileageKm,
            'owners_count' => $ownersCount,
        ]);
    }

    public function ready(): static
    {
        return $this->state(['status' => StockVehicleStatus::Ready]);
    }
}
