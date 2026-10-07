<?php

namespace App\Team;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Enums\WorkspaceStatus;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Invitation lifecycle. Every seat-affecting step runs in a transaction holding the Workspace row
 * lock, so concurrent invitations or acceptances cannot exceed `max_members`. The raw token is
 * generated here, mailed synchronously and never stored, queued or logged.
 */
final class WorkspaceInvitations
{
    /** Session reference set by the invitation landing; proves this browser opened the link. */
    public const SESSION_KEY = 'workspace_invitation.public_id';

    public function __construct(
        private WorkspaceSeats $seats,
        private TeamAuthority $authority,
        private MemberSiteAccess $siteAccess,
    ) {}

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    public static function ttlHours(): int
    {
        return (int) config('workspaces.invitation_ttl_hours');
    }

    /**
     * @param  list<string>  $sitePublicIds
     * @return array{invitation: WorkspaceInvitation, mailed: bool}
     */
    public function invite(
        Workspace $workspace,
        WorkspaceMember $actor,
        string $email,
        WorkspaceRole $role,
        SiteAccessMode $siteAccessMode = SiteAccessMode::AllSites,
        array $sitePublicIds = [],
    ): array {
        $email = self::normalizeEmail($email);

        if (! $this->authority->canAssign($actor->role, $role)) {
            throw ValidationException::withMessages(['role' => 'Эту роль нельзя назначить приглашением.']);
        }

        [$invitation, $token] = DB::transaction(function () use ($workspace, $actor, $email, $role, $siteAccessMode, $sitePublicIds): array {
            $locked = $this->lockWorkspace($workspace);
            $this->ensureNotMember($locked, $email, 'email');

            $pending = WorkspaceInvitation::query()->where('workspace_id', $locked->id)->where('email', $email)->pending()->exists();

            if ($pending) {
                throw ValidationException::withMessages([
                    'email' => 'На этот адрес уже отправлено приглашение. Отправьте его повторно из списка приглашений.',
                ]);
            }

            $siteIds = $this->siteAccess->resolveSites($locked, $role, $siteAccessMode, $sitePublicIds);
            $this->ensureFreeSeat($locked, null, 'email');

            $token = self::newToken();
            $invitation = new WorkspaceInvitation;
            $invitation->forceFill([
                'workspace_id' => $locked->id,
                'invited_by_member_id' => $actor->id,
                'email' => $email,
                'role' => $role,
                'site_access_mode' => $siteIds === [] ? SiteAccessMode::AllSites : SiteAccessMode::SelectedSites,
                'token_hash' => WorkspaceInvitation::hashToken($token),
                'expires_at' => now()->addHours(self::ttlHours()),
            ])->save();
            $invitation->sites()->sync($siteIds);

            return [$invitation, $token];
        });

        $this->log('workspace.invitation_created', $workspace, $invitation);

        return ['invitation' => $invitation, 'mailed' => $this->send($workspace, $invitation, $actor, $token)];
    }

    /**
     * Rotates the token (old links stop working) and extends the expiry.
     */
    public function resend(Workspace $workspace, WorkspaceMember $actor, string $invitationPublicId): bool
    {
        [$invitation, $token] = DB::transaction(function () use ($workspace, $actor, $invitationPublicId): array {
            $locked = $this->lockWorkspace($workspace);
            $invitation = $this->manageableInvitation($locked, $actor, $invitationPublicId);
            $this->ensureNotMember($locked, $invitation->email, 'invitation');

            if ($invitation->isExpired()) {
                $this->ensureFreeSeat($locked, $invitation, 'invitation');
            }

            $token = self::newToken();
            $invitation->forceFill([
                'token_hash' => WorkspaceInvitation::hashToken($token),
                'expires_at' => now()->addHours(self::ttlHours()),
            ])->save();

            return [$invitation, $token];
        });

        $this->log('workspace.invitation_resent', $workspace, $invitation);

        return $this->send($workspace, $invitation, $actor, $token);
    }

    public function cancel(Workspace $workspace, WorkspaceMember $actor, string $invitationPublicId): WorkspaceInvitation
    {
        $invitation = DB::transaction(function () use ($workspace, $actor, $invitationPublicId): WorkspaceInvitation {
            $locked = $this->lockWorkspace($workspace);
            $invitation = $this->manageableInvitation($locked, $actor, $invitationPublicId);
            $invitation->forceFill(['cancelled_at' => now()])->save();

            return $invitation;
        });

        $this->log('workspace.invitation_cancelled', $workspace, $invitation);

        return $invitation;
    }

    public function findByToken(string $token): ?WorkspaceInvitation
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        return WorkspaceInvitation::query()->where('token_hash', WorkspaceInvitation::hashToken($token))->first();
    }

    /**
     * Reason the given user cannot accept right now, or null. Used for display; acceptance
     * re-checks everything under lock.
     */
    public function blocker(WorkspaceInvitation $invitation, ?User $user): ?string
    {
        return match (true) {
            $invitation->accepted_at !== null || $invitation->cancelled_at !== null => 'inactive',
            $invitation->isExpired() => 'expired',
            $invitation->workspace->status !== WorkspaceStatus::Active => 'inactive',
            $user === null => 'guest',
            ! hash_equals($invitation->email, self::normalizeEmail($user->email)) => 'email_mismatch',
            ! $user->hasVerifiedEmail() => 'unverified',
            $invitation->workspace->members()->where('user_id', $user->id)->exists() => 'already_member',
            default => null,
        };
    }

    public function accept(User $user, string $invitationPublicId): Workspace
    {
        $invitation = WorkspaceInvitation::query()->where('public_id', strtolower($invitationPublicId))->first()
            ?? throw self::invalid('Приглашение не найдено.');

        [$workspace, $member] = DB::transaction(function () use ($user, $invitation): array {
            $locked = $this->lockWorkspace($invitation->workspace);
            $invitation = WorkspaceInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();
            $invitation->setRelation('workspace', $locked);

            match ($this->blocker($invitation, $user)) {
                null => null,
                'expired' => throw self::invalid('Срок действия приглашения истёк. Попросите отправить новое приглашение.'),
                'email_mismatch' => throw self::invalid('Приглашение отправлено на другой адрес электронной почты.'),
                'unverified' => throw self::invalid('Сначала подтвердите адрес электронной почты.'),
                'already_member' => throw self::invalid('Вы уже состоите в этом пространстве.'),
                default => throw self::invalid('Приглашение больше не действует.'),
            };

            if (! $this->seats->hasFreeSeat($locked, $invitation)) {
                throw self::invalid('В пространстве нет свободных мест для участников. Обратитесь к администратору пространства.');
            }

            $siteIds = array_values(array_map(
                'intval',
                $invitation->sites()->where('sites.workspace_id', $locked->id)->pluck('sites.id')->all(),
            ));

            if ($invitation->site_access_mode === SiteAccessMode::SelectedSites && $siteIds === []) {
                throw self::invalid('Сайты из приглашения больше недоступны. Попросите отправить новое приглашение.');
            }

            $member = $locked->addMember($user, $invitation->role);
            $this->siteAccess->apply($member, $invitation->site_access_mode, $siteIds);
            $invitation->forceFill(['accepted_at' => now()])->save();

            return [$locked, $member];
        });

        Log::info('workspace.invitation_accepted', [
            'workspace' => $workspace->public_id,
            'invitation' => $invitation->public_id,
            'member' => $member->public_id,
            'role' => $member->role->value,
        ]);

        return $workspace;
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['invitation' => $message]);
    }

    private function lockWorkspace(Workspace $workspace): Workspace
    {
        return Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->firstOrFail();
    }

    private function manageableInvitation(Workspace $workspace, WorkspaceMember $actor, string $publicId): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', strtolower($publicId))
            ->open()
            ->lockForUpdate()
            ->first();

        if ($invitation === null) {
            throw new NotFoundHttpException;
        }

        if (! $this->authority->canAssign($actor->role, $invitation->role)) {
            throw new AccessDeniedHttpException;
        }

        return $invitation;
    }

    private function ensureNotMember(Workspace $workspace, string $email, string $field): void
    {
        $member = $workspace->members()
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->first();

        if ($member === null) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $member->status === WorkspaceMemberStatus::Suspended
                ? 'Этот участник приостановлен. Восстановите его доступ в списке участников.'
                : 'Этот пользователь уже состоит в пространстве.',
        ]);
    }

    private function ensureFreeSeat(Workspace $workspace, ?WorkspaceInvitation $except, string $field): void
    {
        if ($this->seats->hasFreeSeat($workspace, $except)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => $this->seats->limit($workspace) === 0
                ? 'Добавление участников недоступно на текущем тарифе.'
                : 'Достигнут лимит участников на текущем тарифе.',
        ]);
    }

    private function send(Workspace $workspace, WorkspaceInvitation $invitation, WorkspaceMember $actor, string $token): bool
    {
        try {
            Mail::to($invitation->email)->send(new WorkspaceInvitationMail(
                workspaceName: $workspace->name,
                inviterName: $actor->user?->name,
                roleLabel: $invitation->role->label(),
                expiresAt: $invitation->expires_at->format('d.m.Y H:i').' (UTC)',
                url: route('invitations.show', ['token' => $token]),
            ));

            return true;
        } catch (Throwable $exception) {
            Log::warning('workspace.invitation_mail_failed', [
                'workspace' => $workspace->public_id,
                'invitation' => $invitation->public_id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private function log(string $event, Workspace $workspace, WorkspaceInvitation $invitation): void
    {
        Log::info($event, [
            'workspace' => $workspace->public_id,
            'invitation' => $invitation->public_id,
            'role' => $invitation->role->value,
        ]);
    }
}
