<?php

namespace App\Models\Concerns;

use App\Enums\MediaAngle;
use App\Models\VehicleColor;
use App\Models\VehiclePhoto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer-owned colours and photos of a vehicle (ADR-010) plus its catalog Series reference.
 *
 * @property string $catalog_series_ref
 * @property MediaAngle|null $primary_angle
 *
 * @mixin Model
 */
trait HasVehiclePhotos
{
    /**
     * Column of `vehicle_colors` / `vehicle_photos` pointing at this vehicle.
     */
    abstract public function photoOwnerColumn(): string;

    abstract public function photoWorkspaceId(): int;

    /**
     * @return HasMany<VehicleColor, $this>
     */
    public function colors(): HasMany
    {
        return $this->hasMany(VehicleColor::class, $this->photoOwnerColumn())->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<VehiclePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class, $this->photoOwnerColumn())->orderBy('sort_order')->orderBy('id');
    }
}
