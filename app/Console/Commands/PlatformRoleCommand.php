<?php

namespace App\Console\Commands;

use App\Enums\PlatformRole;
use App\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Operator command for explicit platform role assignment. The email only locates an existing
 * account; authorization is always the persisted role, never the email itself.
 */
class PlatformRoleCommand extends Command
{
    protected $signature = 'platform:role
        {action : grant or revoke}
        {email : Email of an existing user}
        {role : super_admin or catalog_manager}
        {--force : Skip the confirmation prompt in production}';

    protected $description = 'Grant or revoke a platform role for an existing user';

    public function handle(): int
    {
        $action = (string) $this->argument('action');
        $role = PlatformRole::tryFrom((string) $this->argument('role'));

        if (! in_array($action, ['grant', 'revoke'], true)) {
            $this->components->error('Action must be "grant" or "revoke".');

            return self::FAILURE;
        }

        if ($role === null) {
            $this->components->error('Unknown role. Use: '.implode(', ', array_column(PlatformRole::cases(), 'value')).'.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->components->error('User not found. Platform roles are granted only to existing accounts.');

            return self::FAILURE;
        }

        if (app()->isProduction() && ! $this->option('force') && ! $this->confirm("{$action} {$role->value} for user #{$user->id}?")) {
            return self::FAILURE;
        }

        if ($action === 'grant') {
            PlatformRoleAssignment::query()->firstOrCreate(['user_id' => $user->id, 'role' => $role->value]);
            $this->components->info("Role {$role->value} granted to user #{$user->id}.");
        } else {
            PlatformRoleAssignment::query()->where('user_id', $user->id)->where('role', $role->value)->delete();
            $this->components->info("Role {$role->value} revoked from user #{$user->id}.");
        }

        return self::SUCCESS;
    }
}
