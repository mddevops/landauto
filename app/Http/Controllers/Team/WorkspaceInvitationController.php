<?php

namespace App\Http\Controllers\Team;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreWorkspaceInvitationRequest;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\WorkspaceContext;
use App\Team\WorkspaceInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class WorkspaceInvitationController extends Controller
{
    private const MAIL_FAILED = 'Приглашение сохранено, но письмо отправить не удалось. Попробуйте «Отправить повторно» позже.';

    public function __construct(private WorkspaceInvitations $invitations) {}

    public function store(StoreWorkspaceInvitationRequest $request, WorkspaceContext $workspaceContext): RedirectResponse
    {
        [$workspace, $actor] = $this->context($workspaceContext);

        $result = $this->invitations->invite(
            $workspace,
            $actor,
            $request->string('email')->toString(),
            WorkspaceRole::from($request->string('role')->toString()),
        );

        Inertia::flash('toast', $result['mailed']
            ? ['type' => 'success', 'message' => 'Приглашение отправлено на '.$result['invitation']->email.'.']
            : ['type' => 'warning', 'message' => self::MAIL_FAILED]);

        return to_route('workspace.team.index');
    }

    public function resend(string $invitation, WorkspaceContext $workspaceContext): RedirectResponse
    {
        Gate::authorize(WorkspacePermission::ManageMembers->value);
        [$workspace, $actor] = $this->context($workspaceContext);

        $mailed = $this->invitations->resend($workspace, $actor, $invitation);

        Inertia::flash('toast', $mailed
            ? ['type' => 'success', 'message' => 'Приглашение отправлено повторно. Предыдущая ссылка больше не действует.']
            : ['type' => 'warning', 'message' => self::MAIL_FAILED]);

        return to_route('workspace.team.index');
    }

    public function destroy(string $invitation, WorkspaceContext $workspaceContext): RedirectResponse
    {
        Gate::authorize(WorkspacePermission::ManageMembers->value);
        [$workspace, $actor] = $this->context($workspaceContext);

        $this->invitations->cancel($workspace, $actor, $invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Приглашение отменено.']);

        return to_route('workspace.team.index');
    }

    /**
     * @return array{Workspace, WorkspaceMember}
     */
    private function context(WorkspaceContext $workspaceContext): array
    {
        $workspace = $workspaceContext->current();
        $actor = $workspaceContext->membership();
        abort_if($workspace === null || $actor === null, 403);

        return [$workspace, $actor];
    }
}
