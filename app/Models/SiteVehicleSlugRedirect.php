<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A previous address of a Site vehicle page; the public runtime redirects it to the current one (ADR-011 §4).
 *
 * @property int $id
 * @property int $site_vehicle_id
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug'])]
class SiteVehicleSlugRedirect extends Model
{
    /**
     * @return BelongsTo<SiteVehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(SiteVehicle::class, 'site_vehicle_id');
    }
}
