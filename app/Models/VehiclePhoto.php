<?php

namespace App\Models;

use App\Enums\MediaAngle;
use App\Models\Concerns\HasImmutablePublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Customer-owned photo file of a vehicle: an angle of a colour (`vehicle_color_id` + `angle`) or
 * a gallery photo (both null). Copied from the platform library or uploaded by the customer.
 * Removed photos are soft-deleted so Published Versions keep serving them.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property int|null $workspace_vehicle_id
 * @property int|null $site_vehicle_id
 * @property int|null $vehicle_color_id
 * @property MediaAngle|null $angle
 * @property string $source
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read VehicleColor|null $color
 */
#[Fillable(['angle', 'source', 'original_name', 'mime_type', 'size_bytes', 'width', 'height', 'sort_order'])]
#[Hidden(['id', 'workspace_id', 'workspace_vehicle_id', 'site_vehicle_id', 'vehicle_color_id', 'path'])]
class VehiclePhoto extends Model
{
    use HasImmutablePublicId, SoftDeletes;

    public const DISK = 'local';

    public const SOURCE_CATALOG = 'catalog';

    public const SOURCE_UPLOAD = 'upload';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'angle' => MediaAngle::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VehicleColor, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(VehicleColor::class, 'vehicle_color_id');
    }

    public function url(): string
    {
        return route('vehicle-photos.show', $this, false);
    }
}
