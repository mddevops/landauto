<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Models\Concerns\HasImmutablePublicId;
use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending access to a Workspace for an email address. Only a SHA-256 hash of the one-time token
 * is stored; the raw token exists only while the invitation email is built.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workspace_id
 * @property int|null $invited_by_member_id
 * @property string $email
 * @property WorkspaceRole $role
 * @property string $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Hidden(['id', 'workspace_id', 'invited_by_member_id', 'token_hash'])]
class WorkspaceInvitation extends Model
{
    /** @use HasFactory<WorkspaceInvitationFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<WorkspaceMember, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(WorkspaceMember::class, 'invited_by_member_id');
    }

    /**
     * Not accepted, not cancelled and not expired: the only state that reserves a seat.
     *
     * @param  Builder<WorkspaceInvitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('cancelled_at')->where('expires_at', '>', now());
    }

    /**
     * Neither accepted nor cancelled; expired ones may still be resent.
     *
     * @param  Builder<WorkspaceInvitation>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('cancelled_at');
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->cancelled_at === null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->isOpen() && ! $this->isExpired();
    }

    public function state(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->cancelled_at !== null => 'cancelled',
            $this->isExpired() => 'expired',
            default => 'pending',
        };
    }
}
