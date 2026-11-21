<?php

namespace App\Platform;

use App\Enums\WorkspaceMemberStatus;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use App\Models\WorkspaceMember;

/**
 * Super Admin directory of registered accounts. Numeric ids and secrets stay off the payload.
 */
final class PlatformUserDirectory
{
    /**
     * @return list<array{name: string, email: string, registered_at: string, roles: string, plans: string, verified: bool, verified_label: string}>
     */
    public function all(): array
    {
        $users = User::query()
            ->with([
                'platformRoleAssignments',
                'memberships' => fn ($query) => $query
                    ->where('status', WorkspaceMemberStatus::Active->value)
                    ->with('workspace.plan:id,name'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $rows = [];

        foreach ($users as $user) {
            $verified = $user->hasVerifiedEmail();
            $rows[] = [
                'name' => $user->name,
                'email' => $user->email,
                'registered_at' => $user->created_at?->toIso8601String() ?? '',
                'roles' => $this->roles($user),
                'plans' => $this->plans($user),
                'verified' => $verified,
                'verified_label' => $verified ? 'Да' : 'Нет',
            ];
        }

        return $rows;
    }

    private function roles(User $user): string
    {
        $platform = $user->platformRoleAssignments
            ->map(fn (PlatformRoleAssignment $assignment): string => $assignment->role->label())
            ->all();

        $workspace = $user->memberships
            ->map(fn (WorkspaceMember $membership): string => $membership->role->label())
            ->unique()
            ->values()
            ->all();

        $labels = array_values(array_unique([...$platform, ...$workspace]));

        return $labels === [] ? 'Нет роли' : implode(', ', $labels);
    }

    private function plans(User $user): string
    {
        $names = $user->memberships
            ->map(fn (WorkspaceMember $membership): ?string => $membership->workspace?->plan?->name)
            ->filter(fn (?string $name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();

        return $names === [] ? 'Нет тарифа' : implode(', ', $names);
    }
}
