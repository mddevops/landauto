<?php

namespace App\Actions\Accounts;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultWorkspacePlan;
use Illuminate\Support\Facades\DB;

class CreateNewAccount
{
    public function __construct(private readonly DefaultWorkspacePlan $defaultPlan) {}

    /**
     * Create the identity and its initial personal Workspace on the default plan as one account operation.
     *
     * @param  array{name: string, email: string, password: string|null, email_verified_at?: mixed}  $attributes
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = new User;
            $user->forceFill($attributes)->save();

            $workspace = new Workspace(['name' => $user->name]);
            $workspace->plan()->associate($this->defaultPlan->resolve());
            $workspace->save();

            $workspace->addMember($user, WorkspaceRole::Owner);

            return $user;
        });
    }
}
