<?php

namespace Database\Factories;

use App\Models\Catalog\AutoSeries;
use App\Models\Workspace;
use App\Models\WorkspaceVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Requires a migrated catalog connection.
 *
 * @extends Factory<WorkspaceVehicle>
 */
class WorkspaceVehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
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
