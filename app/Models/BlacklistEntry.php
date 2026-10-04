<?php

namespace App\Models;

use App\Enums\BlacklistScope;
use App\Enums\BlacklistType;
use App\Models\Concerns\HasImmutablePublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * IP or phone block at exactly one scope (FORMS_AND_INTEGRATIONS.md §13). Global entries are
 * platform-owned and managed only through the audited operator command; Workspace and Site
 * entries belong to their tenant. `value` is always stored normalized.
 *
 * @property int $id
 * @property string $public_id
 * @property BlacklistScope $scope
 * @property int|null $workspace_id
 * @property int|null $site_id
 * @property BlacklistType $type
 * @property string $value
 * @property string|null $reason
 * @property Carbon|null $expires_at
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reason', 'expires_at'])]
#[Hidden(['id', 'workspace_id', 'site_id', 'created_by_user_id'])]
class BlacklistEntry extends Model
{
    use HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => BlacklistScope::class,
            'type' => BlacklistType::class,
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BlacklistEntry $entry): void {
            if ($entry->exists && $entry->isDirty(['scope', 'workspace_id', 'site_id', 'type', 'value'])) {
                throw new LogicException('Blacklist entry scope, owner and value are immutable.');
            }

            $consistent = match ($entry->scope) {
                BlacklistScope::Global => $entry->workspace_id === null && $entry->site_id === null,
                BlacklistScope::Workspace => $entry->workspace_id !== null && $entry->site_id === null,
                BlacklistScope::Site => $entry->workspace_id === null && $entry->site_id !== null,
            };

            if (! $consistent) {
                throw new LogicException('Blacklist entry owner columns must match its scope.');
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
