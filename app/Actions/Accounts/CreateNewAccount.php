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
     * @param  array{name: string, email: string, password: string|null, email_verified_at?: mixed}  $attributes
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = new User;
            $user->forceFill($attributes)->save();
            $workspace = Workspace::create(['name' => $user->name]);

            $workspace->addMember($user, WorkspaceRole::Owner);

            return $user;
        });
    }
}
