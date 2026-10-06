<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Audit record of one restore-to-Draft (P8-009): which Published Version replaced the Draft of
 * which Site, by whom and when. Append-only; the restored version itself is never changed.
 *
 * @property int $id
 * @property int $site_id
 * @property int $published_version_id
 * @property int|null $actor_user_id
 * @property Carbon $restored_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Hidden(['id', 'site_id', 'published_version_id', 'actor_user_id'])]
class SiteVersionRestore extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'restored_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Restore audit records are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Restore audit records are retained.');
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
     * @return BelongsTo<PublishedVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PublishedVersion::class, 'published_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
