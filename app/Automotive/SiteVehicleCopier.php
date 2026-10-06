<?php

namespace App\Automotive;

use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteOfferBenefit;
use App\Models\SiteVehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Explicit Site-to-Site vehicle copy inside one Workspace. Every copy creates new destination
 * rows with new public IDs and no reference back to the source; each vehicle is copied in its
 * own transaction so a failure never leaves a half-created vehicle. Callers authorize the actor
 * on both Sites (including the commercial permissions behind $withOffers / $withBenefits).
 */
final class SiteVehicleCopier
{
    public const COPIED = 'copied';

    public const SKIPPED = 'skipped';

    public const CONFLICT = 'conflict';

    public const FAILED = 'failed';

    public function __construct(private VehicleCatalog $catalog) {}

    /**
     * @param  list<string>  $vehiclePublicIds  source SiteVehicle public IDs, in the requested order
     * @return list<array{vehicle: string, title: string, result: string}>
     */
    public function copy(Site $source, Site $destination, array $vehiclePublicIds, bool $withOffers, bool $withBenefits): array
    {
        if ($source->workspace_id !== $destination->workspace_id || $source->is($destination)) {
            throw new InvalidArgumentException('Vehicles are copied only between different Sites of one Workspace.');
        }

        $withBenefits = $withOffers && $withBenefits;
        $vehicles = $source->vehicles()
            ->whereIn('public_id', $vehiclePublicIds)
            ->with(['offers' => fn ($query) => $query->ordered()->with('benefits')])
            ->get()
            ->keyBy('public_id');
        $titles = $this->catalog->series($vehicles->map(fn (SiteVehicle $vehicle): string => $vehicle->catalog_series_public_id)->values()->all());
        $results = [];

        foreach (array_values(array_unique($vehiclePublicIds)) as $publicId) {
            $vehicle = $vehicles->get($publicId);

            if ($vehicle === null) {
                $results[] = ['vehicle' => $publicId, 'title' => 'Автомобиль не найден', 'result' => self::SKIPPED];

                continue;
            }

            $series = $titles->get($vehicle->catalog_series_public_id);
            $title = $vehicle->custom_name ?? ($series === null ? 'Модель недоступна' : $this->catalog->seriesTitle($series)['title']);
            $result = $destination->vehicles()->where('catalog_series_public_id', $vehicle->catalog_series_public_id)->exists()
                ? self::CONFLICT
                : $this->copyVehicle($vehicle, $destination, $withOffers, $withBenefits);

            $results[] = ['vehicle' => $publicId, 'title' => $title, 'result' => $result];
        }

        Log::info('site.vehicles_copied', [
            'source_site' => $source->public_id,
            'destination_site' => $destination->public_id,
            'results' => array_count_values(array_column($results, 'result')),
        ]);

        return $results;
    }

    private function copyVehicle(SiteVehicle $source, Site $destination, bool $withOffers, bool $withBenefits): string
    {
        try {
            DB::transaction(function () use ($source, $destination, $withOffers, $withBenefits): void {
                $vehicle = new SiteVehicle([
                    'status' => $source->status,
                    'sort_order' => ((int) $destination->vehicles()->max('sort_order')) + 1,
                    'custom_name' => $source->custom_name,
                    'custom_description' => $source->custom_description,
                ]);
                $vehicle->catalog_series_public_id = $source->catalog_series_public_id;
                $vehicle->site()->associate($destination)->save();
                $vehicle->selectMediaSets($source->activeMediaSetPublicIds());

                if ($withOffers) {
                    foreach ($source->offers as $offer) {
                        $this->copyOffer($offer, $vehicle, $withBenefits);
                    }
                }
            });
        } catch (Throwable $exception) {
            Log::warning('site.vehicle_copy_failed', [
                'source_vehicle' => $source->public_id,
                'destination_site' => $destination->public_id,
                'exception' => $exception::class,
            ]);

            return self::FAILED;
        }

        return self::COPIED;
    }

    private function copyOffer(SiteOffer $source, SiteVehicle $vehicle, bool $withBenefits): void
    {
        $offer = new SiteOffer([
            'catalog_equipment_public_id' => $source->catalog_equipment_public_id,
            'price_minor' => $source->price_minor,
            'rrp_minor' => $source->rrp_minor,
            'currency' => $source->currency,
            'availability' => $source->availability,
            'badge' => $source->badge,
            'status' => $source->status,
            'sort_order' => $source->sort_order,
        ]);
        $offer->vehicle()->associate($vehicle)->save();

        if ($withBenefits) {
            $offer->replaceBenefits(array_values($source->benefits->map(fn (SiteOfferBenefit $benefit): array => [
                'type' => $benefit->type,
                'amount_minor' => $benefit->amount_minor,
                'label' => $benefit->label,
            ])->all()));
        }
    }
}
