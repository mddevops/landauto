<?php

namespace App\Team;

use App\Enums\Entitlement;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Support\WorkspaceEntitlements;

/**
 * `max_members` is the number of reserved seats: every membership row (active or suspended)
 * plus pending, unexpired invitations. Removed members, cancelled and expired invitations free
 * their seat. A lowered limit never removes anyone; it only blocks new invitations and acceptances.
 */
final class WorkspaceSeats
{
    public function __construct(private WorkspaceEntitlements $entitlements) {}

    public function limit(Workspace $workspace): int
    {
        return $this->entitlements->limit($workspace, Entitlement::MaxMembers);
    }

    public function reserved(Workspace $workspace, ?WorkspaceInvitation $except = null): int
    {
        $invitations = WorkspaceInvitation::query()->where('workspace_id', $workspace->id)->pending();

        if ($except !== null) {
            $invitations->whereKeyNot($except->getKey());
        }

        return $workspace->members()->count() + $invitations->count();
    }

    /**
     * Callers hold a lock on the Workspace row so concurrent reservations cannot overshoot.
     */
    public function hasFreeSeat(Workspace $workspace, ?WorkspaceInvitation $except = null): bool
    {
        return $this->reserved($workspace, $except) < $this->limit($workspace);
    }
}
