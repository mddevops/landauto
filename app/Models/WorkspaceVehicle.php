<?php

namespace App\Models;

use App\Catalog\AutoCatalog;
use App\Enums\MediaAngle;
use App\Exceptions\InvalidCatalogDataException;
use App\Models\Concerns\HasImmutablePublicId;
use App\Models\Concerns\HasVehiclePhotos;
use Database\Factories\WorkspaceVehicleFactory;
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
 * Vehicle of the customer Catalog (D-083, ADR-010): a catalog Series with the customer's own
 * photo copies and default trims with prices. It is copied explicitly into Sites and never syncs.
 * A Workspace may hold several vehicles of one Series.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property string $catalog_series_ref
 * @property string|null $custom_name
 * @property string|null $custom_description
 * @property MediaAngle|null $primary_angle
 * @property bool $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['custom_name', 'custom_description', 'primary_angle', 'status', 'sort_order'])]
#[Hidden(['id', 'workspace_id'])]
class WorkspaceVehicle extends Model
{
    /** @use HasFactory<WorkspaceVehicleFactory> */
    use HasFactory, HasImmutablePublicId, HasVehiclePhotos;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'boolean', 'sort_order' => 'integer', 'primary_angle' => MediaAngle::class];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkspaceVehicle $vehicle): void {
            if ($vehicle->exists && $vehicle->isDirty(['workspace_id', 'catalog_series_ref'])) {
                throw new LogicException('Workspace vehicle Workspace and Series are immutable.');
            }

            if (! $vehicle->exists && app(AutoCatalog::class)->series($vehicle->catalog_series_ref) === null) {
                throw new InvalidCatalogDataException('Workspace vehicle Series does not exist in the catalog.');
            }
        });
    }

    public function photoOwnerColumn(): string
    {
        return 'workspace_vehicle_id';
    }

    public function photoWorkspaceId(): int
    {
        return $this->workspace_id;
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<WorkspaceVehicleOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(WorkspaceVehicleOffer::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<VehicleCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(VehicleCollection::class, 'vehicle_collection_items')->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
