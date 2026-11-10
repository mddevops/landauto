<?php

namespace App\Actions\Accounts;

use App\Exceptions\DeveloperProfileBlocksDeletionException;
use App\Exceptions\LastWorkspaceOwnerException;
use App\Models\User;
use App\Platform\PlatformSettings;
use App\Support\EndUserSessions;
use Illuminate\Support\Facades\DB;

/**
 * Starts the account-deletion grace period (D-094). The User is logged out and blocked from
 * signing in until `landflow:purge-personal-data` hard-deletes the account after the due date.
 */
final class ScheduleUserDeletion
{
    public function __construct(
        private PlatformSettings $settings,
        private EndUserSessions $sessions,
        private DeleteUserAccount $deleteUserAccount,
    ) {}

    /**
     * @throws LastWorkspaceOwnerException
     * @throws DeveloperProfileBlocksDeletionException
     */
    public function handle(User $user): int
    {
        // Reuse ownership / Developer Profile guards before scheduling.
        $this->deleteUserAccount->assertCanBeDeleted($user);

        $graceDays = $this->settings->accountDeletionGraceDays();
        $dueAt = now()->addDays($graceDays);

        DB::transaction(function () use ($user, $dueAt): void {
            $user->forceFill(['pending_deletion_at' => $dueAt])->save();
            $this->sessions->all($user);
        });

        return $graceDays;
    }
}
