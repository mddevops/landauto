<?php

namespace Database\Factories;

use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaSet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Requires a migrated catalog connection.
 *
 * @extends Factory<SeriesMediaSet>
 */
class SeriesMediaSetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'catalog_series_ref' => fn (): string => (string) AutoSeries::factory()->create()->ref(),
            'name' => 'Цвет '.Str::upper(Str::random(5)),
            'swatch_hex' => '#ffffff',
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function forSeries(AutoSeries $series): static
    {
        return $this->state(['catalog_series_ref' => (string) $series->ref()]);
    }
}
