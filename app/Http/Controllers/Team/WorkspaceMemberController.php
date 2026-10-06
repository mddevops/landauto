<?php

namespace App\Http\Controllers\Team;

use App\Enums\WorkspacePermission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceContext;
use App\Team\WorkspaceMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkspaceMemberController extends Controller
{
    public function __construct(private WorkspaceMembers $members) {}

    public function suspend(string $member, WorkspaceContext $workspaceContext): RedirectResponse
    {
        [$workspace, $actor] = $this->context($workspaceContext);
        $this->members->suspend($workspace, $actor, $member);

        return $this->done('Участник приостановлен. Доступ к пространству закрыт, место остаётся занятым.');
    }

    public function reactivate(string $member, WorkspaceContext $workspaceContext): RedirectResponse
    {
        [$workspace, $actor] = $this->context($workspaceContext);
        $this->members->reactivate($workspace, $actor, $member);

        return $this->done('Доступ участника восстановлен.');
    }

    public function destroy(string $member, WorkspaceContext $workspaceContext): RedirectResponse
    {
        [$workspace, $actor] = $this->context($workspaceContext);
        $this->members->remove($workspace, $actor, $member);

        return $this->done('Участник удалён из пространства. Его учётная запись и другие пространства не затронуты.');
    }

    /**
     * @return array{Workspace, WorkspaceMember}
     */
    private function context(WorkspaceContext $workspaceContext): array
    {
        Gate::authorize(WorkspacePermission::ManageMembers->value);

        $workspace = $workspaceContext->current();
        $actor = $workspaceContext->membership();
        abort_if($workspace === null || $actor === null, 403);

        return [$workspace, $actor];
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('workspace.team.index');
    }
}
