<?php

namespace App\Models\Concerns;

use App\Exceptions\InvalidCatalogDataException;
use App\Models\SeriesMediaSet;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * Selection of platform media sets (references only; files are never copied) for a vehicle that
 * points at a catalog Series through catalog_series_public_id.
 *
 * @property string $catalog_series_public_id
 */
trait SelectsSeriesMediaSets
{
    /**
     * @return BelongsToMany<SeriesMediaSet, $this>
     */
    abstract public function mediaSets(): BelongsToMany;

    /**
     * Replace the selection with active media sets of this vehicle's Series, in the given order.
     *
     * @param  list<string>  $publicIds
     */
    public function selectMediaSets(array $publicIds): void
    {
        $publicIds = array_values(array_unique($publicIds));
        $sets = SeriesMediaSet::query()
            ->where('catalog_series_public_id', $this->catalog_series_public_id)
            ->active()
            ->whereIn('public_id', $publicIds)
            ->pluck('id', 'public_id');

        if ($sets->count() !== count($publicIds)) {
            throw new InvalidCatalogDataException('Only active media sets of the vehicle Series can be selected.');
        }

        $selection = [];

        foreach ($publicIds as $position => $publicId) {
            $selection[$sets[$publicId]] = ['sort_order' => $position];
        }

        DB::transaction(fn () => $this->mediaSets()->sync($selection));
    }

    /**
     * Public IDs of the currently selected sets that are still active, in selection order.
     *
     * @return list<string>
     */
    public function activeMediaSetPublicIds(): array
    {
        return array_values($this->mediaSets()->where('series_media_sets.status', true)->pluck('series_media_sets.public_id')->map(fn ($id): string => (string) $id)->all());
    }
}
