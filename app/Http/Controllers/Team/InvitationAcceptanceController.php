<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Support\WorkspaceContext;
use App\Team\WorkspaceInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The emailed link only drops a session reference to the invitation and redirects, so the raw
 * token leaves the address bar and history. Registration, login and email verification return
 * here through the intended URL; nothing is created on the invitee's behalf.
 */
class InvitationAcceptanceController extends Controller
{
    public function __construct(private WorkspaceInvitations $invitations) {}

    public function show(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findByToken($token);

        if ($invitation === null) {
            $request->session()->forget(WorkspaceInvitations::SESSION_KEY);
        } else {
            $request->session()->put(WorkspaceInvitations::SESSION_KEY, $invitation->public_id);
        }

        return to_route('invitations.pending')->withHeaders(['Referrer-Policy' => 'no-referrer']);
    }

    public function pending(Request $request): Response
    {
        $invitation = $this->sessionInvitation($request);
        $user = $request->user();
        $state = $invitation === null ? 'invalid' : ($this->invitations->blocker($invitation, $user) ?? 'ready');

        if (in_array($state, ['guest', 'unverified'], true)) {
            $request->session()->put('url.intended', route('invitations.pending'));
        }

        $details = $invitation !== null && $invitation->isPending() ? [
            'workspace_name' => $invitation->workspace->name,
            'role_label' => $invitation->role->label(),
            'inviter_name' => $invitation->invitedBy?->user?->name,
            'email' => $this->maskEmail($invitation->email),
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ] : null;

        $response = Inertia::render('auth/workspace-invitation', [
            'state' => $state,
            'invitation' => $details,
        ])->toResponse($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function accept(Request $request, WorkspaceContext $workspaceContext): RedirectResponse
    {
        $invitation = $this->sessionInvitation($request);

        if ($invitation === null) {
            return to_route('invitations.pending');
        }

        $workspace = $this->invitations->accept($request->user(), $invitation->public_id);

        $request->session()->forget(WorkspaceInvitations::SESSION_KEY);
        $request->session()->put(WorkspaceContext::SESSION_KEY, $workspace->public_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Вы присоединились к пространству «'.$workspace->name.'».']);

        return to_route('dashboard');
    }

    private function sessionInvitation(Request $request): ?WorkspaceInvitation
    {
        $publicId = $request->session()->get(WorkspaceInvitations::SESSION_KEY);

        if (! is_string($publicId)) {
            return null;
        }

        return WorkspaceInvitation::query()->with(['workspace', 'invitedBy.user'])->where('public_id', $publicId)->first();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).str_repeat('•', max(1, min(Str::length($local) - 1, 6))).'@'.$domain;
    }
}
