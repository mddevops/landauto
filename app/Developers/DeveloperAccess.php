<?php

namespace App\Developers;

use App\Enums\DeveloperProfileStatus;
use App\Models\DeveloperProfile;
use App\Models\User;

/**
 * Developer Platform identity always comes from the authenticated User's own profile, read fresh
 * on every request, so a suspension applies on the next request. Workspace roles, platform roles
 * and browser input never select or grant it.
 */
final class DeveloperAccess
{
    public function activeProfile(?User $user): ?DeveloperProfile
    {
        if ($user === null) {
            return null;
        }

        return DeveloperProfile::query()
            ->where('user_id', $user->id)
            ->where('status', DeveloperProfileStatus::Active->value)
            ->first();
    }
}
