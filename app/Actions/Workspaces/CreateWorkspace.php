<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultWorkspacePlan;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public function __construct(private readonly DefaultWorkspacePlan $defaultPlan) {}

    /**
     * Create a Workspace on the default plan with the given user as its active Owner (D-100).
     */
    public function create(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name): Workspace {
            $workspace = new Workspace(['name' => $name]);
            $workspace->plan()->associate($this->defaultPlan->resolve());
            $workspace->save();

            $workspace->addMember($owner, WorkspaceRole::Owner);

            return $workspace;
        });
    }
}
