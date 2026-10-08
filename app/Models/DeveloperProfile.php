<?php

namespace App\Models;

use App\Enums\DeveloperProfileStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\DeveloperProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * User-owned creator identity for the Developer Platform (D-093, D-117). It is not a Workspace and
 * grants no Workspace membership, Site access, plan entitlement or platform permission. Only a
 * Super Admin grants, suspends or reactivates it; the owning User never changes.
 *
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property string $display_name
 * @property string $slug
 * @property DeveloperProfileStatus $status
 * @property string|null $bio
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['display_name', 'bio'])]
#[Hidden(['id', 'user_id'])]
class DeveloperProfile extends Model
{
    /** @use HasFactory<DeveloperProfileFactory> */
    use HasFactory, HasImmutablePublicId;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const SLUG_MIN = 3;

    public const SLUG_MAX = 60;

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => DeveloperProfileStatus::class];
    }

    protected static function booted(): void
    {
        static::updating(function (DeveloperProfile $profile): void {
            if ($profile->isDirty('user_id')) {
                throw new LogicException('A Developer Profile always belongs to the User it was granted to.');
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === DeveloperProfileStatus::Active;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Stored creator permission grants (D-118); resolve them through `DeveloperAuthorization`.
     *
     * @return HasMany<DeveloperProfilePermission, $this>
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(DeveloperProfilePermission::class);
    }
}
