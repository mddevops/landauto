<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Internal explicit grant of one Developer creator permission (D-118). The raw key is resolved
 * through `DeveloperPermission::fromKeys()`, so an unknown stored key never grants anything.
 *
 * @property int $id
 * @property int $developer_profile_id
 * @property string $permission
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['permission'])]
class DeveloperProfilePermission extends Model
{
    /**
     * @return BelongsTo<DeveloperProfile, $this>
     */
    public function developerProfile(): BelongsTo
    {
        return $this->belongsTo(DeveloperProfile::class);
    }
}
