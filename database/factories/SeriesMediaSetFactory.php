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
            'catalog_series_public_id' => fn (): string => AutoSeries::factory()->create()->public_id,
            'name' => 'Цвет '.Str::upper(Str::random(5)),
            'swatch_hex' => '#ffffff',
            'status' => true,
            'sort_order' => 0,
        ];
    }

    public function forSeries(AutoSeries $series): static
    {
        return $this->state(['catalog_series_public_id' => $series->public_id]);
    }
}
