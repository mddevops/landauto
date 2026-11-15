<?php

namespace App\Stock;

use App\Automotive\VehiclePhotos;
use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockPtsDocumentType;
use App\Enums\StockVehicleStatus;
use App\Models\StockVehicle;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Stock listing from the auto.ru-like wizard (ADR-012).
 *
 * @phpstan-type Draft array{
 *   condition: StockCondition,
 *   series: string,
 *   equipment: string|null,
 *   year: int,
 *   body_name: string|null,
 *   color_name: string,
 *   color_swatch_hex: string|null,
 *   mileage_km: int|null,
 *   owners_count: int|null,
 *   vin: string|null,
 *   availability: StockAvailability,
 *   price_minor: int|null,
 *   inner_id: string|null,
 *   status: StockVehicleStatus,
 *   pts_document_type: StockPtsDocumentType|null,
 *   purchased_year: int|null,
 *   purchased_month: int|null,
 *   on_warranty: bool,
 *   not_customs_cleared: bool,
 *   description: string|null,
 *   uploads: list<UploadedFile>
 * }
 */
final class StockListingCreator
{
    public function __construct(private VehiclePhotos $photos) {}

    /**
     * @param  Draft  $draft
     */
    public function create(Workspace $workspace, array $draft): StockVehicle
    {
        return DB::transaction(function () use ($workspace, $draft): StockVehicle {
            $vehicle = new StockVehicle([
                'catalog_equipment_ref' => $draft['equipment'],
                'year' => $draft['year'],
                'body_name' => $draft['body_name'],
                'color_name' => $draft['color_name'],
                'color_swatch_hex' => $draft['color_swatch_hex'],
                'mileage_km' => $draft['mileage_km'],
                'owners_count' => $draft['owners_count'],
                'vin' => $draft['vin'],
                'availability' => $draft['availability'],
                'price_minor' => $draft['price_minor'],
                'currency' => Money::DEFAULT_CURRENCY,
                'inner_id' => $draft['inner_id'],
                'status' => $draft['status'],
                'pts_document_type' => $draft['pts_document_type'],
                'purchased_year' => $draft['purchased_year'],
                'purchased_month' => $draft['purchased_month'],
                'on_warranty' => $draft['on_warranty'],
                'not_customs_cleared' => $draft['not_customs_cleared'],
                'description' => $draft['description'],
            ]);
            $vehicle->condition = $draft['condition'];
            $vehicle->catalog_series_ref = $draft['series'];
            $vehicle->workspace()->associate($workspace)->save();

            foreach ($draft['uploads'] as $file) {
                $this->photos->upload($vehicle, $file);
            }

            return $vehicle;
        });
    }
}
