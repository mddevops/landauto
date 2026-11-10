<?php

namespace App\Console\Commands;

use App\Actions\Accounts\DeleteUserAccount;
use App\Exceptions\DeveloperProfileBlocksDeletionException;
use App\Exceptions\LastWorkspaceOwnerException;
use App\Models\Submission;
use App\Models\User;
use App\Platform\PlatformSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies D-094 retention: purge expired Submissions and hard-delete Users past the grace period.
 */
class PurgePersonalDataCommand extends Command
{
    protected $signature = 'landflow:purge-personal-data';

    protected $description = 'Purge expired Submissions and complete scheduled account deletions';

    public function handle(PlatformSettings $settings, DeleteUserAccount $deleteUserAccount): int
    {
        $cutoff = now()->subMonths($settings->submissionRetentionMonths());
        $deletedSubmissions = Submission::query()->where('submitted_at', '<', $cutoff)->delete();

        $dueUsers = User::query()
            ->whereNotNull('pending_deletion_at')
            ->where('pending_deletion_at', '<=', now())
            ->orderBy('id')
            ->get();

        $deletedUsers = 0;

        foreach ($dueUsers as $user) {
            try {
                $deleteUserAccount->delete($user);
                $deletedUsers++;
            } catch (LastWorkspaceOwnerException|DeveloperProfileBlocksDeletionException $exception) {
                Log::warning('landflow.purge_user_blocked', [
                    'user_id' => $user->id,
                    'reason' => $exception::class,
                ]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->info("Submissions purged: {$deletedSubmissions}; accounts deleted: {$deletedUsers}.");

        return self::SUCCESS;
    }
}
