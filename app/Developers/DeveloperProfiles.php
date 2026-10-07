<?php

namespace App\Developers;

use App\Enums\DeveloperProfileStatus;
use App\Models\DeveloperProfile;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Super Admin lifecycle of Developer Profiles (D-093): grant to an existing verified User, suspend
 * and reactivate. Nothing here creates Users, Workspace memberships or platform role assignments,
 * and profiles are never hard-deleted.
 */
final class DeveloperProfiles
{
    /**
     * @param  array{display_name: string, slug: string, bio: string|null}  $data
     */
    public function grant(User $actor, User $owner, array $data): DeveloperProfile
    {
        $profile = new DeveloperProfile([
            'display_name' => $data['display_name'],
            'bio' => $data['bio'],
        ]);
        $profile->slug = $data['slug'];
        $profile->status = DeveloperProfileStatus::Active;
        $profile->user()->associate($owner)->save();

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
