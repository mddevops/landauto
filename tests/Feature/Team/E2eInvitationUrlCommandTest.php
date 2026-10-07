<?php

namespace Tests\Feature\Team;

use App\Models\WorkspaceInvitation;
use App\Team\WorkspaceInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class E2eInvitationUrlCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prints_a_fresh_relative_url_and_stores_only_the_hash(): void
    {
        $invitation = WorkspaceInvitation::factory()->create(['email' => 'invitee@example.com']);
        $oldHash = $invitation->token_hash;

        $this->assertSame(0, Artisan::call('team:e2e-invitation-url', ['email' => 'Invitee@Example.com']));
        $url = trim(Artisan::output());

        $this->assertMatchesRegularExpression('#^/invitations/[a-f0-9]{64}$#', $url);
        $token = substr($url, strlen('/invitations/'));
        $invitation->refresh();
        $this->assertNotSame($oldHash, $invitation->token_hash);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame($invitation->id, app(WorkspaceInvitations::class)->findByToken($token)?->id);
        $this->assertDatabaseMissing('workspace_invitations', ['token_hash' => $token]);
    }

    public function test_ignores_closed_invitations(): void
    {
        WorkspaceInvitation::factory()->cancelled()->create(['email' => 'cancelled@example.com']);
        WorkspaceInvitation::factory()->expired()->create(['email' => 'expired@example.com']);

        $this->assertSame(1, Artisan::call('team:e2e-invitation-url', ['email' => 'cancelled@example.com']));
        $this->assertSame(1, Artisan::call('team:e2e-invitation-url', ['email' => 'expired@example.com']));
    }

    public function test_refuses_outside_testing_and_e2e(): void
    {
        $invitation = WorkspaceInvitation::factory()->create(['email' => 'invitee@example.com']);

        foreach (['local', 'production'] as $environment) {
            $this->app['env'] = $environment;

            $this->assertSame(1, Artisan::call('team:e2e-invitation-url', ['email' => 'invitee@example.com']));
            $this->assertStringNotContainsString('/invitations/', Artisan::output());
        }

        $this->assertSame($invitation->token_hash, $invitation->fresh()?->token_hash);
    }
}
