<?php

namespace App\Support;

use App\Enums\Entitlement;
use App\Enums\WorkspacePermission;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

final class WorkspaceAuthorization
{
    public function __construct(
        private WorkspaceContext $context,
        private WorkspacePermissionResolver $permissions,
        private SiteAccessResolver $siteAccess,
        private WorkspaceEntitlements $entitlements,
    ) {}

    public function allows(User $user, WorkspacePermission $permission): bool
    {
        $membership = $this->context->membership();

        return $this->membershipBelongsTo($membership, $user)
            && $membership->isActive()
            && $this->permissions->roleAllows($membership->role, $permission);
    }

    public function allowsForWorkspace(
        User $user,
        Workspace $workspace,
        WorkspacePermission $permission,
    ): bool {
        return $this->context->current()?->is($workspace) === true
            && $this->allows($user, $permission);
    }

    /**
     * Membership → role permission → Site access (D-088). Every Site-scoped check goes here.
     */
    public function allowsForSite(User $user, Site $site, WorkspacePermission $permission): bool
    {
        return $this->context->current()?->getKey() === $site->workspace_id
            && $this->allows($user, $permission)
            && $this->siteAccess->canAccess($this->context->membership(), $site);
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

        $workspace = $this->context->current();
        $rolePermissions = $this->permissions->forRole($membership->role);

        // manage_stock is role-granted but only exposed when the Workspace may create multi-page Sites (ADR-012).
        if ($workspace === null || ! $this->entitlements->allows($workspace, Entitlement::MultiPageSites)) {
            $rolePermissions = array_values(array_filter(
                $rolePermissions,
                fn (WorkspacePermission $permission): bool => $permission !== WorkspacePermission::ManageStock,
            ));
        }

        return array_map(
            fn (WorkspacePermission $permission): string => $permission->value,
            $rolePermissions,
        );
    }

    private function membershipBelongsTo(?WorkspaceMember $membership, User $user): bool
    {
        return $membership !== null
            && $membership->user_id === $user->getKey()
            && $this->context->current()?->getKey() === $membership->workspace_id;
    }
}
