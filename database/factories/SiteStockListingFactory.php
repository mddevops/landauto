<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteStockListing;
use App\Models\StockVehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteStockListing>
 */
class SiteStockListingFactory extends Factory
{
    protected $model = SiteStockListing::class;

    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'stock_vehicle_id' => StockVehicle::factory(),
        ];
    }
}
