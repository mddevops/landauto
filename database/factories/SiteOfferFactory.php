<?php

namespace Database\Factories;

use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a migrated catalog connection. Without an explicit Equipment the factory creates
 * one under the vehicle's Series so the chain invariant holds.
 *
 * @extends Factory<SiteOffer>
 */
class SiteOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_vehicle_id' => SiteVehicle::factory(),
            'catalog_equipment_public_id' => function (array $attributes): string {
                $vehicle = SiteVehicle::query()->whereKey($attributes['site_vehicle_id'])->firstOrFail();
                $series = AutoSeries::query()->where('public_id', $vehicle->catalog_series_public_id)->firstOrFail();

                return AutoEquipment::factory()
                    ->for(AutoModification::factory()->for($series, 'series'), 'modification')
                    ->create()
                    ->public_id;
            },
            'price_minor' => 185_000_000,
            'rrp_minor' => null,
            'currency' => 'RUB',
            'availability' => null,
            'badge' => null,
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function forEquipment(AutoEquipment $equipment): static
    {
        return $this->state(['catalog_equipment_public_id' => $equipment->public_id]);
    }
}
