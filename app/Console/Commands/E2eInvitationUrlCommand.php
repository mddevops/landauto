<?php

namespace App\Console\Commands;

use App\Models\WorkspaceInvitation;
use App\Team\WorkspaceInvitations;
use Illuminate\Console\Command;

/**
 * E2E helper: rotates the token of the open invitation for an email and prints the relative
 * acceptance URL for the browser test process. Only the new token's hash is stored; the raw
 * token is printed once and never persisted or logged. Refuses outside testing / e2e.
 */
class E2eInvitationUrlCommand extends Command
{
    protected $signature = 'team:e2e-invitation-url {email : Invited email address}';

    protected $description = 'Print a fresh invitation URL for an open invitation (testing/e2e only)';

    public function handle(): int
    {
        if (! app()->environment(['testing', 'e2e'])) {
            $this->error('This command is available only in the testing and e2e environments.');

            return self::FAILURE;
        }

        $invitation = WorkspaceInvitation::query()
            ->where('email', WorkspaceInvitations::normalizeEmail((string) $this->argument('email')))
            ->pending()
            ->latest('id')
            ->first();

        if ($invitation === null) {
            $this->error('No pending invitation for this email.');

            return self::FAILURE;
        }

        $token = bin2hex(random_bytes(32));
        $invitation->forceFill(['token_hash' => WorkspaceInvitation::hashToken($token)])->save();

        $this->line(route('invitations.show', $token, absolute: false));

        return self::SUCCESS;
    }
}
