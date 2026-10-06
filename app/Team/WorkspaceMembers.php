<?php

namespace App\Team;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Member lifecycle. Access is re-resolved from active memberships on every request
 * (WorkspaceContext), so a suspended or removed member loses the Workspace on the next request
 * without touching their sessions. A suspended member keeps the seat; removal frees it.
 */
final class WorkspaceMembers
{
    public function __construct(private TeamAuthority $authority) {}

    public function suspend(Workspace $workspace, WorkspaceMember $actor, string $memberPublicId): WorkspaceMember
    {
        return $this->changeStatus($workspace, $actor, $memberPublicId, WorkspaceMemberStatus::Suspended, 'workspace.member_suspended');
    }

    public function reactivate(Workspace $workspace, WorkspaceMember $actor, string $memberPublicId): WorkspaceMember
    {
        return $this->changeStatus($workspace, $actor, $memberPublicId, WorkspaceMemberStatus::Active, 'workspace.member_reactivated');
    }

    public function remove(Workspace $workspace, WorkspaceMember $actor, string $memberPublicId): WorkspaceMember
    {
        $member = DB::transaction(function () use ($workspace, $actor, $memberPublicId): WorkspaceMember {
            $member = $this->manageableMember($workspace, $actor, $memberPublicId);
            $member->delete();

            return $member;
        });

        $this->log('workspace.member_removed', $workspace, $actor, $member);

        return $member;
    }

    private function changeStatus(
        Workspace $workspace,
        WorkspaceMember $actor,
        string $memberPublicId,
        WorkspaceMemberStatus $status,
        string $event,
    ): WorkspaceMember {
        $member = DB::transaction(function () use ($workspace, $actor, $memberPublicId, $status): WorkspaceMember {
            $member = $this->manageableMember($workspace, $actor, $memberPublicId);

            if ($status === WorkspaceMemberStatus::Active && $member->status !== WorkspaceMemberStatus::Suspended) {
                throw ValidationException::withMessages(['member' => 'Восстановить можно только приостановленного участника.']);
            }

            if ($status === WorkspaceMemberStatus::Suspended && $member->status !== WorkspaceMemberStatus::Active) {
                throw ValidationException::withMessages(['member' => 'Приостановить можно только активного участника.']);
            }

            $member->forceFill([
                'status' => $status,
                'joined_at' => $member->joined_at ?? ($status === WorkspaceMemberStatus::Active ? now() : null),
            ])->save();

            return $member;
        });

        $this->log($event, $workspace, $actor, $member);

        return $member;
    }

    private function manageableMember(Workspace $workspace, WorkspaceMember $actor, string $publicId): WorkspaceMember
    {
        $locked = Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->status !== WorkspaceStatus::Active) {
            throw new NotFoundHttpException;
        }

        $member = WorkspaceMember::query()
            ->where('workspace_id', $locked->id)
            ->where('public_id', strtolower($publicId))
            ->lockForUpdate()
            ->first();

        if ($member === null) {
            throw new NotFoundHttpException;
        }

        if (! $this->authority->canManageMember($actor, $member)) {
            throw new AccessDeniedHttpException;
        }

        return $member;
    }

    private function log(string $event, Workspace $workspace, WorkspaceMember $actor, WorkspaceMember $member): void
    {
        Log::info($event, [
            'workspace' => $workspace->public_id,
            'member' => $member->public_id,
            'actor' => $actor->public_id,
            'role' => $member->role->value,
        ]);
    }
}
