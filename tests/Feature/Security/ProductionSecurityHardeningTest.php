<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Notifications\EmailChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_responses_include_baseline_security_headers(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_password_change_ends_other_database_sessions_and_keeps_the_current_one(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $this->insertSession('other-device', $user);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('id', 'other-device')->count());
        $this->assertTrue(auth()->check());
    }

    public function test_email_change_ends_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create(['email' => 'member@example.com']);
        $this->insertSession('other-device', $user);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new.address@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('id', 'other-device')->count());
        $this->assertTrue(auth()->check());
    }

    public function test_email_changed_notice_includes_configured_support_email(): void
    {
        config(['security.support_email' => 'support@landflow.example']);

        $mail = (new EmailChangedNotification)->toMail(new AnonymousNotifiable);

        $this->assertContains(
            'Если вы не меняли электронную почту, немедленно напишите на support@landflow.example.',
            $mail->introLines,
        );
    }

    public function test_local_disk_serve_is_disabled_by_default(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);
    }
}
