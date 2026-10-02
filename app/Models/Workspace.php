<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceStatus;
use App\Models\Concerns\HasImmutablePublicId;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $plan_id
 * @property string $name
 * @property WorkspaceStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name'])]
#[Hidden(['id'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasImmutablePublicId;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => WorkspaceStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkspaceStatus::class,
        ];
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Active Owner memberships; ownership is defined only by membership role (DATABASE.md §5).
     *
     * @return HasMany<WorkspaceMember, $this>
     */
    public function owners(): HasMany
    {
        return $this->members()
            ->where('role', WorkspaceRole::Owner->value)
            ->where('status', WorkspaceMemberStatus::Active->value);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['public_id', 'role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Membership is created only here so that ownership fields come from server context.
     */
    public function addMember(
        User $user,
        WorkspaceRole $role,
        WorkspaceMemberStatus $status = WorkspaceMemberStatus::Active,
    ): WorkspaceMember {
        $member = new WorkspaceMember;
        $member->forceFill([
            'workspace_id' => $this->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'joined_at' => $status === WorkspaceMemberStatus::Active ? now() : null,
        ])->save();

        return $member;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owners()->where('user_id', $user->id)->exists();
    }
}
