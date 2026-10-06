<?php

namespace Tests\Concerns;

use App\Enums\Entitlement;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

trait BuildsWorkspaceTeam
{
    protected Workspace $workspace;

    protected User $owner;

    protected Plan $plan;

    protected function buildTeamWorkspace(?int $maxMembers = 10): void
    {
        $this->plan = Plan::factory()->create();

        if ($maxMembers !== null) {
            $this->plan->setEntitlement(Entitlement::MaxMembers, $maxMembers);
        }

        $this->workspace = Workspace::factory()->create(['name' => 'Автосалон Север']);
        $this->workspace->plan()->associate($this->plan)->save();
        $this->owner = User::factory()->create(['name' => 'Ольга Владелец']);
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
    }

    protected function teamMember(
        WorkspaceRole $role,
        WorkspaceMemberStatus $status = WorkspaceMemberStatus::Active,
        ?Workspace $workspace = null,
        ?User $user = null,
    ): WorkspaceMember {
        return ($workspace ?? $this->workspace)->addMember($user ?? User::factory()->create(), $role, $status);
    }

    protected function setMaxMembers(int $limit): void
    {
        $this->plan->setEntitlement(Entitlement::MaxMembers, $limit);
    }
}
