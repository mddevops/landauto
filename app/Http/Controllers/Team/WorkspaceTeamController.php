<?php

namespace App\Http\Controllers\Team;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Support\SiteAccessResolver;
use App\Support\WorkspaceContext;
use App\Team\TeamAuthority;
use App\Team\WorkspaceSeats;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team of the current Workspace. Exposes public IDs only; no numeric membership, user, Site or
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

        $sites = $workspace->sites()->orderBy('name')->get(['id', 'public_id', 'name']);
        $sitePublicIds = $sites->pluck('public_id', 'id');

        $members = $workspace->members()
            ->with(['user:id,name,email', 'sites:sites.id'])
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
                'can_manage' => $authority->canManageMember($actor, $member),
                'site_access_mode' => SiteAccessResolver::effectiveMode($member)->value,
                'sites' => $member->sites->map(fn (Site $site) => $sitePublicIds[$site->id] ?? null)->filter()->values(),
            ])
            ->values();

        $invitations = $workspace->invitations()
            ->open()
            ->withCount('sites')
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
                'site_access_mode' => $invitation->site_access_mode->value,
                'site_count' => (int) $invitation->getAttribute('sites_count'),
            ])
            ->values();

        return Inertia::render('workspaces/team', [
            'members' => $members,
            'invitations' => $invitations,
            'sites' => $sites->map(fn (Site $site): array => ['public_id' => $site->public_id, 'name' => $site->name])->values(),
            'seats' => [
                'limit' => $seats->limit($workspace),
                'reserved' => $seats->reserved($workspace),
            ],
            'assignableRoles' => array_map(
                fn (WorkspaceRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                $authority->assignableRoles($actor->role),
            ),
            'allSitesRoles' => array_values(array_map(
                fn (WorkspaceRole $role): string => $role->value,
                array_filter(WorkspaceRole::cases(), fn (WorkspaceRole $role): bool => SiteAccessResolver::forcesAllSites($role)),
            )),
            'invitationTtlHours' => (int) config('workspaces.invitation_ttl_hours'),
        ]);
    }
}
