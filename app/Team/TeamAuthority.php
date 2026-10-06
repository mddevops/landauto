<?php

namespace App\Team;

use App\Enums\WorkspaceRole;
use App\Models\WorkspaceMember;

/**
 * Which roles a member may hand out or manage. The `manage_members` permission decides whether a
 * member manages the team at all; this policy limits whom: nobody grants or manages Owner, and an
 * Admin only works with roles below Admin. Ownership transfer is a separate future flow.
 */
final class TeamAuthority
{
    /**
     * @return list<WorkspaceRole>
     */
    public function assignableRoles(WorkspaceRole $actor): array
    {
        return match ($actor) {
            WorkspaceRole::Owner => $this->rolesExcept([WorkspaceRole::Owner]),
            WorkspaceRole::Admin => $this->rolesExcept([WorkspaceRole::Owner, WorkspaceRole::Admin]),
            default => [],
        };
    }

    public function canAssign(WorkspaceRole $actor, WorkspaceRole $role): bool
    {
        return in_array($role, $this->assignableRoles($actor), true);
    }

    /**
     * Suspend, reactivate or remove: never oneself and never an Owner.
     */
    public function canManageMember(WorkspaceMember $actor, WorkspaceMember $target): bool
    {
        return ! $actor->is($target) && $this->canAssign($actor->role, $target->role);
    }

    /**
     * @param  list<WorkspaceRole>  $excluded
     * @return list<WorkspaceRole>
     */
    private function rolesExcept(array $excluded): array
    {
        return array_values(array_filter(
            WorkspaceRole::cases(),
            fn (WorkspaceRole $role): bool => ! in_array($role, $excluded, true),
        ));
    }
}
