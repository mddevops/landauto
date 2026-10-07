<?php

namespace App\Models;

use App\Catalog\CatalogReferences;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use App\Models\Concerns\SelectsSeriesMediaSets;
use Database\Factories\SiteVehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Site-owned vehicle page at catalog Series level (D-104). The catalog row lives in another
 * database, so the Series is referenced by public_id and validated by the application.
 * source_workspace_vehicle_id is provenance only (D-083): the library never changes this copy.
 *
 * @property int $id
 * @property string $public_id
 * @property int $site_id
 * @property int|null $source_workspace_vehicle_id
 * @property string $catalog_series_public_id
 * @property string|null $custom_name
 * @property string|null $custom_description
 * @property bool $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['status', 'sort_order', 'custom_name', 'custom_description'])]
#[Hidden(['id', 'site_id', 'source_workspace_vehicle_id'])]
class SiteVehicle extends Model
{
    /** @use HasFactory<SiteVehicleFactory> */
    use HasFactory, HasImmutablePublicId, SelectsSeriesMediaSets;

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
     * @return BelongsTo<WorkspaceVehicle, $this>
     */
    public function sourceWorkspaceVehicle(): BelongsTo
    {
        return $this->belongsTo(WorkspaceVehicle::class, 'source_workspace_vehicle_id');
    }

    /**
     * @return HasMany<SiteOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(SiteOffer::class);
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
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
