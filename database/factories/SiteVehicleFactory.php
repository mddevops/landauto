<?php

namespace Database\Factories;

use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Models\SiteVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a migrated catalog connection.
 *
 * @extends Factory<SiteVehicle>
 */
class SiteVehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'catalog_series_ref' => fn (): string => (string) AutoSeries::factory()->create()->ref(),
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function forSeries(AutoSeries $series): static
    {
        return $this->state(['catalog_series_ref' => (string) $series->ref()]);
    }
}
