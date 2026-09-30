<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Email/password accounts get no access to the protected area until the email is verified (D-095).
 *
 * Allowed for authenticated unverified users: verification notice, verification link, resend,
 * logout, the settings redirect, profile edit/update (to fix a mistyped email) and password confirmation.
 */
class UnverifiedUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_is_redirected_from_dashboard_to_verification_notice()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_is_redirected_from_security_settings_to_verification_notice()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_is_redirected_from_appearance_settings_to_verification_notice()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_cannot_update_password()
    {
        $user = User::factory()->unverified()->create();
        $originalPassword = $user->password;

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame($originalPassword, $user->refresh()->password);
    }

    public function test_unverified_user_cannot_delete_account()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('verification.notice'));

        $this->assertNotNull($user->fresh());
    }

    public function test_verified_user_can_access_the_protected_area()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('appearance.edit'))->assertOk();
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk();
    }

    public function test_unverified_user_can_open_the_verification_notice()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_unverified_user_can_open_profile_settings()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/settings')->assertRedirect('/settings/profile');
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_unverified_user_can_update_profile()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Исправленное имя',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Исправленное имя', $user->refresh()->name);
    }

    public function test_unverified_user_can_open_password_confirmation()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('password.confirm'))->assertOk();
    }

    public function test_unverified_user_can_log_out()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
