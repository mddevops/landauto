<?php

namespace App\Console\Commands;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultWorkspacePlan;
use App\Support\FullWorkspacePlan;
use Illuminate\Console\Command;

/**
 * Operator command that puts a Workspace owned by an existing user on a system plan. The email only
 * locates the account; the Workspace must be owned by it.
 */
class PlanAssignCommand extends Command
{
    protected $signature = 'plan:assign
        {email : Email of an existing Workspace owner}
        {plan=full : full or free}
        {--workspace= : Public ID of the Workspace when the user owns several}
        {--force : Skip the confirmation prompt in production}';

    protected $description = 'Assign a system plan to a Workspace owned by an existing user';

    public function handle(FullWorkspacePlan $full, DefaultWorkspacePlan $free): int
    {
        $plan = match ((string) $this->argument('plan')) {
            Plan::FULL_KEY => $full->resolve(),
            Plan::FREE_KEY => $free->resolve(),
            default => null,
        };

        if ($plan === null) {
            $this->components->error('Unknown plan. Use: full, free.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', mb_strtolower(trim((string) $this->argument('email'))))->first();

        if ($user === null) {
            $this->components->error('User not found.');

            return self::FAILURE;
        }

        $owned = $user->workspaces()
            ->wherePivot('role', WorkspaceRole::Owner->value)
            ->wherePivot('status', WorkspaceMemberStatus::Active->value)
            ->when($this->option('workspace'), fn ($query, $publicId) => $query->where('workspaces.public_id', $publicId))
            ->get();

        if ($owned->isEmpty()) {
            $this->components->error('The user owns no matching Workspace.');

            return self::FAILURE;
        }

        if ($owned->count() > 1) {
            $this->components->error('The user owns several Workspaces; pass --workspace=<public_id>.');
            $this->table(['public_id', 'name'], $owned->map(fn (Workspace $workspace): array => [$workspace->public_id, $workspace->name])->all());

            return self::FAILURE;
        }

        /** @var Workspace $workspace */
        $workspace = $owned->first();

        if (app()->isProduction() && ! $this->option('force') && ! $this->confirm("Assign plan {$plan->key} to Workspace {$workspace->public_id}?")) {
            return self::FAILURE;
        }

        $workspace->plan()->associate($plan)->save();
        $this->components->info("Plan {$plan->key} assigned to Workspace {$workspace->public_id} ({$workspace->name}).");

        return self::SUCCESS;
    }
}
