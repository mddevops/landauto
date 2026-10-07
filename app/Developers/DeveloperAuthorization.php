<?php

namespace App\Developers;

use App\Enums\DeveloperPermission;
use App\Models\DeveloperProfile;
use App\Models\User;

/**
 * Central, deny-by-default Developer creator authorization (D-118). A permission is effective only
 * when it is explicitly stored for the authenticated User's own active profile. Workspace roles,
 * platform roles, plans and browser input never grant one, and a permission never authorizes
 * content owned by another Developer Profile.
 */
final class DeveloperAuthorization
{
    public function __construct(private DeveloperAccess $access) {}

    /**
     * Stored grants regardless of status (kept through a suspension), read fresh.
     *
     * @return list<DeveloperPermission>
     */
    public function granted(DeveloperProfile $profile): array
    {
        return DeveloperPermission::fromKeys($profile->permissions()->pluck('permission'));
    }

    /**
     * Effective permissions: none while the profile is suspended.
     *
     * @return list<DeveloperPermission>
     */
    public function permissions(DeveloperProfile $profile): array
    {
        return $profile->isActive() ? $this->granted($profile) : [];
    }

    public function allows(DeveloperProfile $profile, DeveloperPermission $permission): bool
    {
        return in_array($permission, $this->permissions($profile), true);
    }

    public function allowsUser(?User $user, DeveloperPermission $permission): bool
    {
        $profile = $this->access->activeProfile($user);

        return $profile !== null && $this->allows($profile, $permission);
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(DeveloperProfile $profile): array
    {
        $effective = $this->permissions($profile);
        $capabilities = [];

        foreach (DeveloperPermission::cases() as $permission) {
            $capabilities[$permission->value] = in_array($permission, $effective, true);
        }

        return $capabilities;
    }
}
