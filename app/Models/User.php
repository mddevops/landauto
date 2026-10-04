<?php

namespace App\Models;

use App\Enums\WorkspaceMemberStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    /**
     * @return HasMany<UserAuthIdentity, $this>
     */
    public function authIdentities(): HasMany
    {
        return $this->hasMany(UserAuthIdentity::class);
    }

    /**
     * @return HasMany<PlatformRoleAssignment, $this>
     */
    public function platformRoleAssignments(): HasMany
    {
        return $this->hasMany(PlatformRoleAssignment::class);
    }

    public function hasPassword(): bool
    {
        return is_string($this->password) && $this->password !== '';
    }

    /**
     * All Workspaces with a membership in any status; only active memberships grant access (TENANCY.md §4).
     *
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['public_id', 'role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Workspace, $this>
     */
    public function activeWorkspaces(): BelongsToMany
    {
        return $this->workspaces()->wherePivot('status', WorkspaceMemberStatus::Active->value);
    }

    public function activeMembershipIn(Workspace $workspace): ?WorkspaceMember
    {
        return $this->memberships()
            ->where('workspace_id', $workspace->id)
            ->where('status', WorkspaceMemberStatus::Active->value)
            ->first();
    }
}
