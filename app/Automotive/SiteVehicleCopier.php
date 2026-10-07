<?php

namespace App\Automotive;

use App\Enums\BenefitType;
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
 * on both Sites (including the commercial permissions behind offers / benefits).
 *
 * Conflict identity is destination Site + catalog Series. A conflict is skipped unless the caller
 * passes explicit update fields for it; offers are matched by catalog Equipment, missing offers
 * are created and unrelated destination offers are never deleted. When the destination holds
 * several offers for an Equipment the update would touch, the whole vehicle update is refused
 * as ambiguous and nothing on it changes.
 */
final class SiteVehicleCopier
{
    public const COPIED = 'copied';

    public const UPDATED = 'updated';

    public const SKIPPED = 'skipped';

    public const CONFLICT = 'conflict';

    public const AMBIGUOUS = 'ambiguous';

    public const FAILED = 'failed';

    public const FIELD_TEXT = 'text';

    public const FIELD_MEDIA = 'media';

    public const FIELD_STATUS = 'status';

    public const FIELD_OFFERS = 'offers';

    public const FIELD_BENEFITS = 'benefits';

    public const FIELDS = [self::FIELD_TEXT, self::FIELD_MEDIA, self::FIELD_STATUS, self::FIELD_OFFERS, self::FIELD_BENEFITS];

    public function __construct(private VehicleCatalog $catalog) {}

    /**
     * @param  list<string>  $vehiclePublicIds  source SiteVehicle public IDs, in the requested order
     * @param  array<string, list<string>>  $updates  conflict update fields keyed by source vehicle public ID
     * @return list<array{vehicle: string, title: string, result: string}>
     */
    public function copy(Site $source, Site $destination, array $vehiclePublicIds, bool $withOffers, bool $withBenefits, array $updates = []): array
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
            $existing = $destination->vehicles()->where('catalog_series_public_id', $vehicle->catalog_series_public_id)->first();
            $fields = array_values(array_intersect(self::FIELDS, $updates[$publicId] ?? []));

            $result = match (true) {
                $existing === null => $this->copyVehicle($vehicle, $destination, $withOffers, $withBenefits),
                $fields === [] => self::CONFLICT,
                default => $this->updateVehicle($vehicle, $existing, $fields),
            };

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
        return $this->atomically($source, $destination, self::COPIED, function () use ($source, $destination, $withOffers, $withBenefits): void {
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
    }

    /**
     * @param  list<string>  $fields
     */
    private function updateVehicle(SiteVehicle $source, SiteVehicle $target, array $fields): string
    {
        $site = $target->site;
        $touchesOffers = in_array(self::FIELD_OFFERS, $fields, true) || in_array(self::FIELD_BENEFITS, $fields, true);

        if ($touchesOffers && $this->hasDuplicateTargetOffers($source, $target)) {
            return self::AMBIGUOUS;
        }

        return $this->atomically($source, $site, self::UPDATED, function () use ($source, $target, $fields): void {
            if (in_array(self::FIELD_TEXT, $fields, true)) {
                $target->fill(['custom_name' => $source->custom_name, 'custom_description' => $source->custom_description]);
            }

            if (in_array(self::FIELD_STATUS, $fields, true)) {
                $target->status = $source->status;
            }

            $target->save();

            if (in_array(self::FIELD_MEDIA, $fields, true)) {
                $target->selectMediaSets($source->activeMediaSetPublicIds());
            }

            $withOffers = in_array(self::FIELD_OFFERS, $fields, true);
            $withBenefits = in_array(self::FIELD_BENEFITS, $fields, true);

            if (! $withOffers && ! $withBenefits) {
                return;
            }

            $matches = $target->offers()->get()->keyBy('catalog_equipment_public_id');

            foreach ($source->offers as $offer) {
                /** @var SiteOffer|null $match */
                $match = $matches->get($offer->catalog_equipment_public_id);

                if ($match === null) {
                    if ($withOffers) {
                        $this->copyOffer($offer, $target, $withBenefits);
                    }

                    continue;
                }

                if ($withOffers) {
                    $match->update($offer->only(['price_minor', 'rrp_minor', 'currency', 'availability', 'badge']));
                }

                if ($withBenefits) {
                    $match->replaceBenefits($this->benefits($offer));
                }
            }
        });
    }

    private function hasDuplicateTargetOffers(SiteVehicle $source, SiteVehicle $target): bool
    {
        $equipment = $source->offers->pluck('catalog_equipment_public_id')->unique()->values()->all();

        return $target->offers()
            ->whereIn('catalog_equipment_public_id', $equipment)
            ->selectRaw('catalog_equipment_public_id, count(*) as offers_count')
            ->groupBy('catalog_equipment_public_id')
            ->havingRaw('count(*) > 1')
            ->exists();
    }

    private function copyOffer(SiteOffer $source, SiteVehicle $vehicle, bool $withBenefits): void
    {
        $offer = new SiteOffer($source->only(['catalog_equipment_public_id', 'price_minor', 'rrp_minor', 'currency', 'availability', 'badge', 'status', 'sort_order']));
        $offer->vehicle()->associate($vehicle)->save();

        if ($withBenefits) {
            $offer->replaceBenefits($this->benefits($source));
        }
    }

    /**
     * @return list<array{type: BenefitType, amount_minor: int, label: string|null}>
     */
    private function benefits(SiteOffer $offer): array
    {
        return array_values($offer->benefits->map(fn (SiteOfferBenefit $benefit): array => [
            'type' => $benefit->type,
            'amount_minor' => $benefit->amount_minor,
            'label' => $benefit->label,
        ])->all());
    }

    private function atomically(SiteVehicle $source, ?Site $destination, string $success, callable $operation): string
    {
        try {
            DB::transaction(fn () => $operation());
        } catch (Throwable $exception) {
            Log::warning('site.vehicle_copy_failed', [
                'source_vehicle' => $source->public_id,
                'destination_site' => $destination?->public_id,
                'exception' => $exception::class,
            ]);

            return self::FAILED;
        }

        return $success;
    }
}
