<?php

namespace App\Platform;

use App\Enums\PlatformRole;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultWorkspacePlan;
use App\Support\FullWorkspacePlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Super Admin beta access panel (D-133): look up a User by email, assign system Workspace plans
 * and platform roles. Authorization is always the persisted assignment, never the email itself.
 */
final class PlatformAccess
{
    public function __construct(
        private FullWorkspacePlan $full,
        private DefaultWorkspacePlan $free,
    ) {}

    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->with('platformRoleAssignments')
            ->first();
    }

    /**
     * @return array{email: string, name: string, verified: bool, roles: list<string>, workspaces: list<array{public_id: string, name: string, plan_key: string|null, plan_name: string|null}>}
     */
    public function present(User $user): array
    {
        $workspaces = $user->workspaces()
            ->wherePivot('role', WorkspaceRole::Owner->value)
            ->wherePivot('status', WorkspaceMemberStatus::Active->value)
            ->with('plan:id,key,name')
            ->orderBy('workspaces.id')
            ->get();

        return [
            'email' => $user->email,
            'name' => $user->name,
            'verified' => $user->hasVerifiedEmail(),
            'roles' => array_values($user->platformRoleAssignments
                ->map(fn (PlatformRoleAssignment $assignment): string => $assignment->role->value)
                ->all()),
            'workspaces' => array_values($workspaces->map(fn (Workspace $workspace): array => [
                'public_id' => $workspace->public_id,
                'name' => $workspace->name,
                'plan_key' => $workspace->plan?->key,
                'plan_name' => $workspace->plan?->name,
            ])->all()),
        ];
    }

    /**
     * @param  list<string>  $roles
     */
    public function syncRoles(User $actor, User $user, array $roles): void
    {
        $wanted = [];

        foreach ($roles as $value) {
            $role = PlatformRole::tryFrom($value);

            if ($role === null) {
                throw ValidationException::withMessages(['roles' => 'Неизвестная роль платформы.']);
            }

            $wanted[$role->value] = $role;
        }

        if ($actor->is($user) && ! isset($wanted[PlatformRole::SuperAdmin->value])) {
            throw ValidationException::withMessages(['roles' => 'Нельзя снять с себя роль суперадминистратора.']);
        }

        if (! isset($wanted[PlatformRole::SuperAdmin->value]) && $this->isSoleSuperAdmin($user)) {
            throw ValidationException::withMessages(['roles' => 'Нельзя снять последнего суперадминистратора.']);
        }

        DB::transaction(function () use ($user, $wanted): void {
            $current = PlatformRoleAssignment::query()->where('user_id', $user->id)->lockForUpdate()->get()
                ->keyBy(fn (PlatformRoleAssignment $assignment): string => $assignment->role->value);

            foreach ($wanted as $value => $role) {
                if (! $current->has($value)) {
                    PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role->value]);
                }
            }

            foreach ($current as $value => $assignment) {
                if (! isset($wanted[$value])) {
                    $assignment->delete();
                }
            }
        });

        Log::info('platform.access_roles_synced', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $user->id,
            'target_email' => $user->email,
            'roles' => array_keys($wanted),
        ]);
    }

    public function assignPlan(User $actor, User $owner, Workspace $workspace, string $planKey): Plan
    {
        $plan = match ($planKey) {
            Plan::FULL_KEY => $this->full->resolve(),
            Plan::FREE_KEY => $this->free->resolve(),
            default => null,
        };

        if ($plan === null) {
            throw ValidationException::withMessages(['plan' => 'Неизвестный тариф. Доступны: free, full.']);
        }

        $owns = $owner->workspaces()
            ->wherePivot('role', WorkspaceRole::Owner->value)
            ->wherePivot('status', WorkspaceMemberStatus::Active->value)
            ->whereKey($workspace->id)
            ->exists();

        if (! $owns) {
            throw ValidationException::withMessages(['workspace' => 'Пользователь не является владельцем этого пространства.']);
        }

        $workspace->plan()->associate($plan)->save();

        Log::info('platform.access_plan_assigned', [
            'actor_user_id' => $actor->id,
            'target_user_id' => $owner->id,
            'target_email' => $owner->email,
            'workspace_public_id' => $workspace->public_id,
            'plan' => $plan->key,
        ]);

        return $plan;
    }

    private function isSoleSuperAdmin(User $user): bool
    {
        $has = PlatformRoleAssignment::query()
            ->where('user_id', $user->id)
            ->where('role', PlatformRole::SuperAdmin->value)
            ->exists();

        if (! $has) {
            return false;
        }

        return PlatformRoleAssignment::query()
            ->where('role', PlatformRole::SuperAdmin->value)
            ->where('user_id', '!=', $user->id)
            ->doesntExist();
    }
}
