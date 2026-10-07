<?php

namespace App\Models;

use App\Catalog\CatalogReferences;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use App\Models\Concerns\SelectsSeriesMediaSets;
use Database\Factories\WorkspaceVehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Reusable Workspace Vehicle Library entry at catalog Series level (D-083). It is a source layer
 * that is copied explicitly into SiteVehicle; it owns no commercial data (prices, benefits,
 * availability, badges, CTA stay SiteOffer-owned) and never syncs into Sites.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property string $catalog_series_public_id
 * @property string|null $custom_name
 * @property string|null $custom_description
 * @property bool $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['custom_name', 'custom_description', 'status', 'sort_order'])]
#[Hidden(['id', 'workspace_id'])]
class WorkspaceVehicle extends Model
{
    /** @use HasFactory<WorkspaceVehicleFactory> */
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
        static::saving(function (WorkspaceVehicle $vehicle): void {
            if ($vehicle->exists && $vehicle->isDirty(['workspace_id', 'catalog_series_public_id'])) {
                throw new LogicException('Workspace vehicle Workspace and Series are immutable.');
            }

            if (! $vehicle->exists && app(CatalogReferences::class)->series($vehicle->catalog_series_public_id) === null) {
                throw new InvalidCatalogDataException('Workspace vehicle Series does not exist in the catalog.');
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsToMany<SeriesMediaSet, $this>
     */
    public function mediaSets(): BelongsToMany
    {
        return $this->belongsToMany(SeriesMediaSet::class, 'workspace_vehicle_media_sets')
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
