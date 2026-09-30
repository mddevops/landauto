<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Exceptions\LastWorkspaceOwnerException;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\WorkspaceMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * First-class membership entity (ADR-001), not a technical pivot.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property int $user_id
 * @property WorkspaceRole $role
 * @property WorkspaceMemberStatus $status
 * @property Carbon|null $joined_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['role', 'status', 'joined_at'])]
#[Hidden(['id', 'workspace_id', 'user_id'])]
class WorkspaceMember extends Model
{
    /** @use HasFactory<WorkspaceMemberFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => WorkspaceMemberStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'status' => WorkspaceMemberStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * Guards run for Eloquent model operations; bulk query-builder updates bypass them and must not be used for memberships.
     */
    protected static function booted(): void
    {
        static::updating(function (WorkspaceMember $member): void {
            if ($member->isDirty(['workspace_id', 'user_id'])) {
                throw new LogicException('Membership workspace and user are immutable.');
            }

            if ($member->wasActiveOwner() && ! $member->isActiveOwner()) {
                $member->ensureAnotherActiveOwnerExists();
            }
        });

        static::deleting(function (WorkspaceMember $member): void {
            if ($member->wasActiveOwner()) {
                $member->ensureAnotherActiveOwnerExists();
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === WorkspaceMemberStatus::Active;
    }

    public function isActiveOwner(): bool
    {
        return $this->role === WorkspaceRole::Owner && $this->isActive();
    }

    private function wasActiveOwner(): bool
    {
        return $this->getRawOriginal('role') === WorkspaceRole::Owner->value
            && $this->getRawOriginal('status') === WorkspaceMemberStatus::Active->value;
    }

    private function ensureAnotherActiveOwnerExists(): void
    {
        $otherOwners = static::query()
            ->where('workspace_id', $this->getRawOriginal('workspace_id'))
            ->whereKeyNot($this->getKey())
            ->where('role', WorkspaceRole::Owner->value)
            ->where('status', WorkspaceMemberStatus::Active->value)
            ->lockForUpdate()
            ->exists();

        if (! $otherOwners) {
            throw new LastWorkspaceOwnerException;
        }
    }
}
