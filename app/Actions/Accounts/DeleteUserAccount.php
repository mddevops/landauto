<?php

namespace App\Actions\Accounts;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Exceptions\DeveloperProfileBlocksDeletionException;
use App\Exceptions\LastWorkspaceOwnerException;
use App\Models\User;
use App\Models\WorkspaceMember;
use Closure;
use Illuminate\Support\Facades\DB;

class DeleteUserAccount
{
    public function delete(User $user, ?Closure $beforeDelete = null): void
    {
        DB::transaction(function () use ($user, $beforeDelete): void {
            // Durable creator identity (D-093): future authored content depends on it.
            if ($user->developerProfile()->exists()) {
                throw new DeveloperProfileBlocksDeletionException;
            }

            /** @var list<WorkspaceMember> $memberships */
            $memberships = $user->memberships()
                ->with('workspace')
                ->lockForUpdate()
                ->get()
                ->all();

            $this->ensureOwnedWorkspacesCanBeLeft($memberships);
            $beforeDelete?->__invoke();

            foreach ($memberships as $membership) {
                $workspace = $membership->workspace;

                if ($membership->isActiveOwner() && $workspace->members()->count() === 1) {
                    // The account's empty personal Workspace has no business data or other members to preserve.
                    $workspace->delete();

                    continue;
                }

                $membership->delete();
            }

            $this->deleteSessions($user);
            $user->delete();
        });
    }

    /**
     * @param  list<WorkspaceMember>  $memberships
     */
    private function ensureOwnedWorkspacesCanBeLeft(array $memberships): void
    {
        foreach ($memberships as $membership) {
            if (! $membership->isActiveOwner() || $membership->workspace->members()->count() === 1) {
                continue;
            }

            $hasAnotherOwner = WorkspaceMember::query()
                ->where('workspace_id', $membership->workspace_id)
                ->whereKeyNot($membership->getKey())
                ->where('role', WorkspaceRole::Owner->value)
                ->where('status', WorkspaceMemberStatus::Active->value)
                ->lockForUpdate()
                ->exists();

            if (! $hasAnotherOwner) {
                throw new LastWorkspaceOwnerException;
            }
        }
    }

    private function deleteSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
