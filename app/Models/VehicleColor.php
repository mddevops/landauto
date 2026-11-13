<?php

namespace App\Models;

use App\Models\Concerns\HasImmutablePublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Customer-owned colour of a Workspace or Site vehicle, holding its angle photos.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property int|null $workspace_vehicle_id
 * @property int|null $site_vehicle_id
 * @property string $name
 * @property string|null $swatch_hex
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'swatch_hex', 'sort_order'])]
#[Hidden(['id', 'workspace_id', 'workspace_vehicle_id', 'site_vehicle_id'])]
class VehicleColor extends Model
{
    use HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return HasMany<VehiclePhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(VehiclePhoto::class);
    }
}
