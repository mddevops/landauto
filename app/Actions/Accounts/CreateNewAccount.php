<?php

namespace App\Actions\Accounts;

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateNewAccount
{
    public function __construct(private readonly CreateWorkspace $createWorkspace) {}

    /**
     * Create the identity and its initial Workspace on the default plan as one account operation.
     *
     * @param  array{name: string, email: string, password: string|null, email_verified_at?: mixed}  $attributes
     */
    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = new User;
            $user->forceFill($attributes)->save();

            $this->createWorkspace->create($user, Workspace::DEFAULT_NAME);

            return $user;
        });
    }
}
