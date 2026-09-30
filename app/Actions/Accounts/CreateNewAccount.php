<?php

namespace App\Actions\Accounts;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateNewAccount
{
    /**
     * Create the identity and its initial personal Workspace as one account operation.
     *
     * @param  array{name: string, email: string, password: string, email_verified_at?: mixed}  $attributes
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::create($attributes);
            $workspace = Workspace::create(['name' => $user->name]);

            $workspace->addMember($user, WorkspaceRole::Owner);

            return $user;
        });
    }
}
