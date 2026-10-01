<?php

namespace App\Support;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkspaceContext
{
    public const SESSION_KEY = 'workspace.current_public_id';

    /** @var Collection<int, WorkspaceMember> */
    private Collection $memberships;

    private ?WorkspaceMember $currentMembership = null;

    public function __construct()
    {
        $this->memberships = new Collection;
    }

    public function resolve(User $user, Session $session): void
    {
        $this->memberships = $user->memberships()
            ->where('status', WorkspaceMemberStatus::Active->value)
            ->whereHas('workspace', fn ($query) => $query->where('status', WorkspaceStatus::Active->value))
            ->with('workspace')
            ->orderBy('id')
            ->get();

        $selectedPublicId = $session->get(self::SESSION_KEY);
        $this->currentMembership = is_string($selectedPublicId)
            ? $this->membershipByWorkspacePublicId($selectedPublicId)
            : null;

        $this->currentMembership ??= $this->memberships->first();

        if ($this->currentMembership === null) {
            $session->forget(self::SESSION_KEY);

            return;
        }

        $session->put(self::SESSION_KEY, $this->current()->public_id);
    }

    public function switchTo(string $workspacePublicId, Session $session): Workspace
    {
        $membership = $this->membershipByWorkspacePublicId($workspacePublicId);

        if ($membership === null) {
            throw new NotFoundHttpException;
        }

        $this->currentMembership = $membership;
        $session->put(self::SESSION_KEY, $membership->workspace->public_id);

        return $membership->workspace;
    }

    public function current(): ?Workspace
    {
        return $this->currentMembership?->workspace;
    }

    public function membership(): ?WorkspaceMember
    {
        return $this->currentMembership;
    }

    /**
     * @return Collection<int, Workspace>
     */
    public function available(): Collection
    {
        return $this->memberships->map(
            fn (WorkspaceMember $membership): Workspace => $membership->workspace,
        );
    }

    private function membershipByWorkspacePublicId(string $publicId): ?WorkspaceMember
    {
        return $this->memberships->first(
            fn (WorkspaceMember $membership): bool => hash_equals($membership->workspace->public_id, $publicId),
        );
    }
}
