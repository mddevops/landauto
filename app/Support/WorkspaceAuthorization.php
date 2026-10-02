<?php

namespace App\Support;

use App\Enums\WorkspacePermission;
use App\Models\User;
use App\Models\WorkspaceMember;

final class WorkspaceAuthorization
{
    public function __construct(
        private WorkspaceContext $context,
        private WorkspacePermissionResolver $permissions,
    ) {}

    public function allows(User $user, WorkspacePermission $permission): bool
    {
        $membership = $this->context->membership();

        return $this->membershipBelongsTo($membership, $user)
            && $membership->isActive()
            && $this->permissions->roleAllows($membership->role, $permission);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(User $user): array
    {
        $membership = $this->context->membership();

        if (! $this->membershipBelongsTo($membership, $user) || ! $membership->isActive()) {
            return [];
        }

        return array_map(
            fn (WorkspacePermission $permission): string => $permission->value,
            $this->permissions->forRole($membership->role),
        );
    }

    private function membershipBelongsTo(?WorkspaceMember $membership, User $user): bool
    {
        return $membership !== null
            && $membership->user_id === $user->getKey()
            && $this->context->current()?->getKey() === $membership->workspace_id;
    }
}
