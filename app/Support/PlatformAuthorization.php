<?php

namespace App\Support;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\PlatformRoleAssignment;
use App\Models\User;

/**
 * Platform authorization from explicit persistent role assignments only (D-058).
 */
final class PlatformAuthorization
{
    /**
     * @return list<PlatformPermission>
     */
    public function permissionsForRole(PlatformRole $role): array
    {
        return match ($role) {
            PlatformRole::SuperAdmin => PlatformPermission::cases(),
            PlatformRole::CatalogManager => [
                PlatformPermission::ViewCatalog,
                PlatformPermission::EditCatalog,
                PlatformPermission::ManageCatalogMedia,
            ],
        };
    }

    /**
     * @return list<PlatformRole>
     */
    public function roles(User $user): array
    {
        return $user->platformRoleAssignments
            ->map(fn (PlatformRoleAssignment $assignment): PlatformRole => $assignment->role)
            ->values()
            ->all();
    }

    /**
     * @return list<PlatformPermission>
     */
    public function permissions(User $user): array
    {
        $permissions = [];

        foreach ($this->roles($user) as $role) {
            foreach ($this->permissionsForRole($role) as $permission) {
                $permissions[$permission->value] = $permission;
            }
        }

        return array_values($permissions);
    }

    public function allows(User $user, PlatformPermission $permission): bool
    {
        return in_array($permission, $this->permissions($user), true);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(User $user): array
    {
        return array_map(fn (PlatformPermission $permission): string => $permission->value, $this->permissions($user));
    }
}
