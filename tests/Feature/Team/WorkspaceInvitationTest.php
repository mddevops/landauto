<?php

namespace Tests\Feature\Team;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Support\WorkspaceContext;
use App\Team\WorkspaceInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\Concerns\BuildsWorkspaceTeam;
use Tests\TestCase;

class WorkspaceInvitationTest extends TestCase
{
    use BuildsWorkspaceTeam, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->buildTeamWorkspace(maxMembers: 3);
    }

    public function test_team_page_lists_members_and_invitations_with_public_ids_only(): void
    {
        $this->teamMember(WorkspaceRole::Designer, WorkspaceMemberStatus::Suspended);
        $invitation = WorkspaceInvitation::factory()->for($this->workspace)->create(['email' => 'guest@example.com']);

        $response = $this->actingAs($this->owner)->get(route('workspace.team.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('workspaces/team')
            ->has('members', 2)
            ->where('members.0.role', 'owner')
            ->where('members.0.is_self', true)
            ->where('members.1.status', 'suspended')
            ->has('invitations', 1)
            ->where('invitations.0.public_id', $invitation->public_id)
            ->where('invitations.0.state', 'pending')
            ->where('seats', ['limit' => 3, 'reserved' => 3])
            ->has('assignableRoles', 3));

        $content = (string) $response->getContent();
        $this->assertStringNotContainsString($invitation->token_hash, $content);
        $this->assertStringNotContainsString('"workspace_id"', $content);
        $this->assertStringNotContainsString('"user_id"', $content);
    }

    public function test_team_page_requires_manage_members(): void
    {
        $designer = $this->teamMember(WorkspaceRole::Designer);

        $this->actingAs($designer->user)->get(route('workspace.team.index'))->assertForbidden();
        $this->actingAs($designer->user)
            ->post(route('workspace.team.invitations.store'), ['email' => 'x@example.com', 'role' => 'designer'])
            ->assertForbidden();
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_owner_invites_with_hashed_single_use_token_and_russian_mail(): void
    {
        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), ['email' => '  New.Person@Example.COM ', 'role' => 'designer'])
            ->assertRedirect(route('workspace.team.index'))
            ->assertSessionHasNoErrors();

        $invitation = WorkspaceInvitation::query()->sole();
        $this->assertSame('new.person@example.com', $invitation->email);
        $this->assertSame(WorkspaceRole::Designer, $invitation->role);
        $this->assertNotNull($invitation->invited_by_member_id);
        $this->assertEqualsWithDelta(now()->addHours(168)->timestamp, $invitation->expires_at->timestamp, 5);

        $token = $this->sentToken('new.person@example.com');
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('workspace_invitations', ['token_hash' => $token]);
        $this->assertNull(User::query()->where('email', 'new.person@example.com')->first());

        $rendered = (new WorkspaceInvitationMail('Автосалон Север', 'Ольга Владелец', 'Дизайнер', '13.10.2026 10:00 (UTC)', 'https://app.test/invitations/'.$token))->render();
        $this->assertStringContainsString('Принять приглашение', $rendered);
        $this->assertStringContainsString('Ольга Владелец', $rendered);
        $this->assertStringContainsString('«Дизайнер»', $rendered);
    }

    public function test_owner_cannot_invite_owner_and_admin_cannot_invite_admin(): void
    {
        $admin = $this->teamMember(WorkspaceRole::Admin);

        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), ['email' => 'a@example.com', 'role' => 'owner'])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin->user)
            ->post(route('workspace.team.invitations.store'), ['email' => 'b@example.com', 'role' => 'admin'])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin->user)
            ->post(route('workspace.team.invitations.store'), ['email' => 'c@example.com', 'role' => 'content_editor'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['c@example.com'], WorkspaceInvitation::query()->pluck('email')->all());
    }

    public function test_zero_member_limit_denies_invitations(): void
    {
        $this->setMaxMembers(0);

        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), ['email' => 'a@example.com', 'role' => 'designer'])
            ->assertSessionHasErrors(['email' => 'Добавление участников недоступно на текущем тарифе.']);

        $this->actingAs($this->owner)->get(route('workspace.team.index'))
            ->assertInertia(fn (Assert $page) => $page->where('seats', ['limit' => 0, 'reserved' => 1]));
        $this->assertDatabaseCount('workspace_invitations', 0);
    }

    public function test_seats_count_active_suspended_and_pending_but_not_expired_or_cancelled(): void
    {
        $this->teamMember(WorkspaceRole::Designer, WorkspaceMemberStatus::Suspended);
        WorkspaceInvitation::factory()->for($this->workspace)->expired()->create();
        WorkspaceInvitation::factory()->for($this->workspace)->cancelled()->create();
        WorkspaceInvitation::factory()->for($this->workspace)->create(['accepted_at' => now()]);

        $this->invite('third@example.com')->assertSessionHasNoErrors();
        $this->invite('fourth@example.com')
            ->assertSessionHasErrors(['email' => 'Достигнут лимит участников на текущем тарифе.']);
    }

    public function test_seat_limit_holds_for_back_to_back_invitations(): void
    {
        $this->setMaxMembers(2);

        $this->invite('first@example.com')->assertSessionHasNoErrors();
        $this->invite('second@example.com')->assertSessionHasErrors('email');

        $this->assertSame(1, WorkspaceInvitation::query()->pending()->count());
    }

    public function test_existing_members_and_pending_invitations_are_not_duplicated(): void
    {
        $active = $this->teamMember(WorkspaceRole::Designer);
        $suspended = $this->teamMember(WorkspaceRole::Designer, WorkspaceMemberStatus::Suspended);
        $this->setMaxMembers(10);

        $this->invite(strtoupper($active->user->email))
            ->assertSessionHasErrors(['email' => 'Этот пользователь уже состоит в пространстве.']);
        $this->invite($suspended->user->email)
            ->assertSessionHasErrors(['email' => 'Этот участник приостановлен. Восстановите его доступ в списке участников.']);

        $this->invite('pending@example.com')->assertSessionHasNoErrors();
        $this->invite('Pending@example.com')->assertSessionHasErrors('email');
    }

    public function test_expired_or_cancelled_invitations_may_be_recreated_and_other_workspaces_may_invite_the_same_email(): void
    {
        WorkspaceInvitation::factory()->for($this->workspace)->expired()->create(['email' => 'again@example.com']);
        WorkspaceInvitation::factory()->for($this->workspace)->cancelled()->create(['email' => 'again@example.com']);

        $this->invite('again@example.com')->assertSessionHasNoErrors();

        $other = Workspace::factory()->create();
        $other->plan()->associate($this->plan)->save();
        $other->addMember($this->owner, WorkspaceRole::Owner);
        $this->withSession([WorkspaceContext::SESSION_KEY => $other->public_id]);

        $this->invite('again@example.com')->assertSessionHasNoErrors();
        $this->assertSame(1, WorkspaceInvitation::query()->where('workspace_id', $other->id)->count());
    }

    public function test_resend_rotates_token_and_extends_expiry(): void
    {
        $this->invite('rotate@example.com');
        $oldToken = $this->sentToken('rotate@example.com');
        $invitation = WorkspaceInvitation::query()->sole();
        $this->travel(2)->days();

        $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.resend', $invitation->public_id))
            ->assertRedirect(route('workspace.team.index'));

        $invitation->refresh();
        $newToken = collect(Mail::sent(WorkspaceInvitationMail::class))->last()->url;
        $newToken = substr($newToken, strrpos($newToken, '/') + 1);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(hash('sha256', $newToken), $invitation->token_hash);
        $this->assertEqualsWithDelta(now()->addHours(168)->timestamp, $invitation->expires_at->timestamp, 5);

        $this->get(route('invitations.show', $oldToken))->assertRedirect(route('invitations.pending'));
        $this->get(route('invitations.pending'))->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    }

    public function test_cancel_invalidates_link_and_frees_the_seat(): void
    {
        $this->setMaxMembers(2);
        $this->invite('cancel@example.com');
        $token = $this->sentToken('cancel@example.com');
        $invitation = WorkspaceInvitation::query()->sole();

        $this->actingAs($this->owner)
            ->delete(route('workspace.team.invitations.destroy', $invitation->public_id))
            ->assertRedirect(route('workspace.team.index'));

        $this->assertNotNull($invitation->refresh()->cancelled_at);
        $this->invite('next@example.com')->assertSessionHasNoErrors();

        $invitee = User::factory()->create(['email' => 'cancel@example.com']);
        $this->actingAs($invitee)->get(route('invitations.show', $token));
        $this->actingAs($invitee)->get(route('invitations.pending'))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'inactive')->where('invitation', null));
        $this->actingAs($invitee)->post(route('invitations.accept'))->assertSessionHasErrors('invitation');
        $this->assertFalse($this->workspace->members()->where('user_id', $invitee->id)->exists());
    }

    public function test_admin_cannot_manage_admin_invitations_or_other_workspace_invitations(): void
    {
        $admin = $this->teamMember(WorkspaceRole::Admin);
        $this->setMaxMembers(10);
        $adminInvite = WorkspaceInvitation::factory()->for($this->workspace)->create(['role' => WorkspaceRole::Admin]);
        $foreign = WorkspaceInvitation::factory()->create();

        $this->actingAs($admin->user)->post(route('workspace.team.invitations.resend', $adminInvite->public_id))->assertForbidden();
        $this->actingAs($admin->user)->delete(route('workspace.team.invitations.destroy', $adminInvite->public_id))->assertForbidden();
        $this->actingAs($this->owner)->post(route('workspace.team.invitations.resend', $foreign->public_id))->assertNotFound();
        $this->actingAs($this->owner)->delete(route('workspace.team.invitations.destroy', $foreign->public_id))->assertNotFound();

        $this->assertNull($adminInvite->refresh()->cancelled_at);
        $this->assertNull($foreign->refresh()->cancelled_at);
        Mail::assertNothingSent();
    }

    public function test_landing_strips_token_keeps_session_reference_and_sets_no_referrer(): void
    {
        $token = $this->inviteAndGetToken('guest@example.com');
        $invitation = WorkspaceInvitation::query()->sole();
        auth()->logout();

        $this->get(route('invitations.show', $token))
            ->assertRedirect(route('invitations.pending'))
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSessionHas(WorkspaceInvitations::SESSION_KEY, $invitation->public_id);

        $response = $this->get(route('invitations.pending'))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSessionHas('url.intended', route('invitations.pending'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/workspace-invitation')
                ->where('state', 'guest')
                ->where('invitation.workspace_name', 'Автосалон Север')
                ->where('invitation.role_label', 'Дизайнер')
                ->where('invitation.inviter_name', 'Ольга Владелец')
                ->where('invitation.email', 'g••••@example.com'));

        $this->assertStringNotContainsString($token, (string) $response->getContent());
        $this->assertStringNotContainsString($invitation->token_hash, (string) $response->getContent());
        $this->assertNull(User::query()->where('email', 'guest@example.com')->first());
    }

    public function test_guest_logs_in_and_returns_to_the_invitation(): void
    {
        $invitee = User::factory()->create(['email' => 'guest@example.com']);
        $token = $this->inviteAndGetToken('guest@example.com');
        auth()->logout();

        $this->get(route('invitations.show', $token));
        $this->get(route('invitations.pending'));

        $this->post(route('login.store'), ['email' => 'guest@example.com', 'password' => 'password'])
            ->assertRedirect(route('invitations.pending'));
        $this->get(route('invitations.pending'))->assertInertia(fn (Assert $page) => $page->where('state', 'ready'));
        $this->assertAuthenticatedAs($invitee);
    }

    public function test_matching_verified_user_accepts_and_switches_workspace_keeping_other_memberships(): void
    {
        $invitee = User::factory()->create(['email' => 'join@example.com']);
        $home = Workspace::factory()->create();
        $home->addMember($invitee, WorkspaceRole::Owner);
        $token = $this->inviteAndGetToken('JOIN@example.com', 'content_editor');

        $this->actingAs($invitee)->get(route('invitations.show', $token));
        $this->actingAs($invitee)->post(route('invitations.accept'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(WorkspaceContext::SESSION_KEY, $this->workspace->public_id)
            ->assertSessionMissing(WorkspaceInvitations::SESSION_KEY);

        $member = $this->workspace->members()->where('user_id', $invitee->id)->sole();
        $this->assertSame(WorkspaceRole::ContentEditor, $member->role);
        $this->assertSame(WorkspaceMemberStatus::Active, $member->status);
        $this->assertNotNull($member->joined_at);
        $this->assertNotNull(WorkspaceInvitation::query()->sole()->accepted_at);
        $this->assertTrue($home->members()->where('user_id', $invitee->id)->exists());
        $this->assertSame(2, User::query()->count());

        $this->actingAs($invitee)->post(route('invitations.accept'))->assertRedirect(route('invitations.pending'));
        $this->get(route('invitations.show', $token));
        $this->actingAs($invitee)->post(route('invitations.accept'))->assertSessionHasErrors('invitation');
        $this->assertSame(1, $this->workspace->members()->where('user_id', $invitee->id)->count());
    }

    public function test_wrong_user_cannot_accept(): void
    {
        $other = User::factory()->create(['email' => 'other@example.com']);
        $token = $this->inviteAndGetToken('right@example.com');

        $this->actingAs($other)->get(route('invitations.show', $token));
        $this->actingAs($other)->get(route('invitations.pending'))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'email_mismatch'));
        $this->actingAs($other)->post(route('invitations.accept'))
            ->assertSessionHasErrors(['invitation' => 'Приглашение отправлено на другой адрес электронной почты.']);

        $this->assertFalse($this->workspace->members()->where('user_id', $other->id)->exists());
    }

    public function test_unverified_user_cannot_accept(): void
    {
        $invitee = User::factory()->unverified()->create(['email' => 'new@example.com']);
        $token = $this->inviteAndGetToken('new@example.com');

        $this->actingAs($invitee)->get(route('invitations.show', $token));
        $this->actingAs($invitee)->get(route('invitations.pending'))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'unverified'))
            ->assertSessionHas('url.intended', route('invitations.pending'));
        $this->actingAs($invitee)->post(route('invitations.accept'))->assertRedirect(route('verification.notice'));

        $this->assertFalse($this->workspace->members()->where('user_id', $invitee->id)->exists());
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $invitee = User::factory()->create(['email' => 'late@example.com']);
        $token = $this->inviteAndGetToken('late@example.com');
        $this->travel(169)->hours();

        $this->actingAs($invitee)->get(route('invitations.show', $token));
        $this->actingAs($invitee)->get(route('invitations.pending'))
            ->assertInertia(fn (Assert $page) => $page->where('state', 'expired'));
        $this->actingAs($invitee)->post(route('invitations.accept'))->assertSessionHasErrors('invitation');

        $this->assertFalse($this->workspace->members()->where('user_id', $invitee->id)->exists());
    }

    public function test_lowered_limit_blocks_acceptance_but_never_removes_members(): void
    {
        $this->teamMember(WorkspaceRole::Designer);
        $invitee = User::factory()->create(['email' => 'blocked@example.com']);
        $token = $this->inviteAndGetToken('blocked@example.com');
        $this->setMaxMembers(1);

        $this->actingAs($invitee)->get(route('invitations.show', $token));
        $this->actingAs($invitee)->post(route('invitations.accept'))->assertSessionHasErrors('invitation');

        $this->assertSame(2, $this->workspace->members()->count());
        $this->assertNull(WorkspaceInvitation::query()->sole()->accepted_at);
    }

    public function test_mail_failure_keeps_invitation_and_shows_retry_feedback(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

        $this->invite('retry@example.com')
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'warning');

        $this->assertTrue(WorkspaceInvitation::query()->pending()->where('email', 'retry@example.com')->exists());
    }

    public function test_raw_token_never_reaches_logs_or_database(): void
    {
        $logged = [];
        $this->app['events']->listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event->message.' '.json_encode($event->context);
        });

        $token = $this->inviteAndGetToken('audit@example.com');
        $invitee = User::factory()->create(['email' => 'audit@example.com']);
        $this->actingAs($invitee)->get(route('invitations.show', $token));
        $this->actingAs($invitee)->post(route('invitations.accept'));

        $this->assertContains('workspace.invitation_created', array_map(fn (string $line) => strtok($line, ' '), $logged));
        $this->assertContains('workspace.invitation_accepted', array_map(fn (string $line) => strtok($line, ' '), $logged));
        foreach ($logged as $line) {
            $this->assertStringNotContainsString($token, $line);
        }

        $row = (array) DB::table('workspace_invitations')->first();
        $this->assertNotContains($token, array_map('strval', $row));
        $sessions = DB::getSchemaBuilder()->hasTable('sessions') ? DB::table('sessions')->pluck('payload')->implode('') : '';
        $this->assertStringNotContainsString($token, base64_decode($sessions) ?: $sessions);
    }

    private function invite(string $email, string $role = 'designer'): TestResponse
    {
        return $this->actingAs($this->owner)
            ->post(route('workspace.team.invitations.store'), ['email' => $email, 'role' => $role]);
    }

    private function inviteAndGetToken(string $email, string $role = 'designer'): string
    {
        $this->invite($email, $role)->assertSessionHasNoErrors();

        return $this->sentToken(strtolower($email));
    }

    private function sentToken(string $email): string
    {
        $url = '';
        Mail::assertSent(WorkspaceInvitationMail::class, function (WorkspaceInvitationMail $mail) use ($email, &$url): bool {
            if (! $mail->hasTo($email)) {
                return false;
            }
            $url = $mail->url;

            return true;
        });

        return substr($url, strrpos($url, '/') + 1);
    }
}
