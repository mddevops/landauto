<?php

namespace App\Models;

use App\Catalog\CatalogReferences;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\SiteVehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Site-owned vehicle page at catalog Series level (D-104). The catalog row lives in another
 * database, so the Series is referenced by public_id and validated by the application.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property string $catalog_series_public_id
 * @property bool $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['status', 'sort_order'])]
#[Hidden(['id', 'site_id'])]
class SiteVehicle extends Model
{
    /** @use HasFactory<SiteVehicleFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (SiteVehicle $vehicle): void {
            if ($vehicle->exists && $vehicle->isDirty(['site_id', 'catalog_series_public_id'])) {
                throw new LogicException('Site vehicle Site and Series are immutable.');
            }

            if (! $vehicle->exists && app(CatalogReferences::class)->series($vehicle->catalog_series_public_id) === null) {
                throw new InvalidCatalogDataException('Site vehicle Series does not exist in the catalog.');
            }
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Platform media sets selected for this vehicle (references only; files are never copied).
     *
     * @return BelongsToMany<SeriesMediaSet, $this>
     */
    public function mediaSets(): BelongsToMany
    {
        return $this->belongsToMany(SeriesMediaSet::class, 'site_vehicle_media_sets')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('series_media_sets.id');
    }

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
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
