<?php

namespace App\Developers;

use App\Enums\DeveloperPermission;
use App\Enums\DeveloperProfileStatus;
use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Super Admin lifecycle of Developer Profiles (D-093, D-118): grant to an existing verified User,
 * suspend, reactivate and set explicit creator permissions. Nothing here creates Users, Workspace
 * memberships or platform role assignments, and profiles are never hard-deleted.
 */
final class DeveloperProfiles
{
    /**
     * @param  array{display_name: string, slug: string, bio: string|null}  $data
     */
    public function grant(User $actor, User $owner, array $data): DeveloperProfile
    {
        $profile = DB::transaction(function () use ($owner, $data): DeveloperProfile {
            $profile = new DeveloperProfile([
                'display_name' => $data['display_name'],
                'bio' => $data['bio'],
            ]);
            $profile->slug = $data['slug'];
            $profile->status = DeveloperProfileStatus::Active;
            $profile->user()->associate($owner)->save();

            $profile->permissions()->createMany(array_map(
                fn (DeveloperPermission $permission): array => ['permission' => $permission->value],
                DeveloperPermission::defaults(),
            ));

            return $profile;
        });

        Log::info('developer.profile_created', [
            'developer_profile' => $profile->public_id,
            'actor_user_id' => $actor->id,
        ]);

        return $profile;
    }

    public function suspend(User $actor, DeveloperProfile $profile): void
    {
        $this->changeStatus($actor, $profile, DeveloperProfileStatus::Suspended, 'developer.profile_suspended');
    }

    public function reactivate(User $actor, DeveloperProfile $profile): void
    {
        $this->changeStatus($actor, $profile, DeveloperProfileStatus::Active, 'developer.profile_reactivated');
    }

    /**
     * Replaces the stored grant set atomically; the status is untouched, so a suspended profile
     * keeps its grants for reactivation.
     *
     * @param  list<DeveloperPermission>  $permissions
     */
    public function syncPermissions(User $actor, DeveloperProfile $profile, array $permissions): void
    {
        $keys = array_map(fn (DeveloperPermission $permission): string => $permission->value, DeveloperPermission::fromKeys(
            array_map(fn (DeveloperPermission $permission): string => $permission->value, $permissions),
        ));

        $changed = DB::transaction(function () use ($profile, $keys): bool {
            DeveloperProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();

            $current = $profile->permissions()->pluck('permission')->all();
            $removed = array_values(array_diff($current, $keys));
            $added = array_values(array_diff($keys, $current));

            if ($removed !== []) {
                $profile->permissions()->whereIn('permission', $removed)->delete();
            }

            if ($added !== []) {
                $profile->permissions()->createMany(array_map(fn (string $key): array => ['permission' => $key], $added));
            }

            return $removed !== [] || $added !== [];
        });

        if (! $changed) {
            return;
        }

        Log::info('developer.permissions_updated', [
            'developer_profile' => $profile->public_id,
            'permissions' => $keys,
            'actor_user_id' => $actor->id,
        ]);
    }

    private function changeStatus(User $actor, DeveloperProfile $profile, DeveloperProfileStatus $status, string $event): void
    {
        if ($profile->status === $status) {
            return;
        }

        $profile->status = $status;
        $profile->save();

        Log::info($event, [
            'developer_profile' => $profile->public_id,
            'actor_user_id' => $actor->id,
        ]);
    }
}
