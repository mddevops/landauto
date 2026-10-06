<?php

namespace App\Http\Controllers\Team;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceContext;
use App\Team\TeamAuthority;
use App\Team\WorkspaceSeats;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team of the current Workspace. Exposes public IDs only; no numeric membership, user or
 * Workspace IDs, and never invitation token hashes.
 */
class WorkspaceTeamController extends Controller
{
    private const INVITATIONS_SHOWN = 100;

    public function index(WorkspaceContext $workspaceContext, TeamAuthority $authority, WorkspaceSeats $seats): Response
    {
        Gate::authorize(WorkspacePermission::ManageMembers->value);

        $workspace = $workspaceContext->current();
        $actor = $workspaceContext->membership();
        abort_if($workspace === null || $actor === null, 403);

        $members = $workspace->members()
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkspaceMember $member): array => [
                'public_id' => $member->public_id,
                'name' => $member->user->name ?? '',
                'email' => $member->user->email ?? '',
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'status' => $member->status->value,
                'joined_at' => $member->joined_at?->toIso8601String(),
                'is_self' => $member->is($actor),
            ])
            ->values();

        $invitations = $workspace->invitations()
            ->open()
            ->latest('id')
            ->limit(self::INVITATIONS_SHOWN)
            ->get()
            ->map(fn (WorkspaceInvitation $invitation): array => [
                'public_id' => $invitation->public_id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'state' => $invitation->state(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'can_manage' => $authority->canAssign($actor->role, $invitation->role),
            ])
            ->values();

        return Inertia::render('workspaces/team', [
            'members' => $members,
            'invitations' => $invitations,
            'seats' => [
                'limit' => $seats->limit($workspace),
                'reserved' => $seats->reserved($workspace),
            ],
            'assignableRoles' => array_map(
                fn (WorkspaceRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                $authority->assignableRoles($actor->role),
            ),
            'invitationTtlHours' => (int) config('workspaces.invitation_ttl_hours'),
        ]);
    }
}
