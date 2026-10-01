<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Notifications\EmailChangedNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_name_only_update_requires_no_password_and_keeps_verification()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Новое имя',
                'email' => ' Member@Example.com ',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Новое имя', $user->name);
        $this->assertSame('member@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_email_change_without_current_password_is_rejected()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'attacker@example.com',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('member@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_email_change_with_wrong_current_password_is_rejected()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('member@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_email_change_with_current_password_requires_reverification_and_notifies_old_address()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => '  New.Address@Example.COM ',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('new.address@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification, array $channels, User $notifiable) => $channels === ['mail']
                && $notifiable->email === 'new.address@example.com'
                && $notifiable->routeNotificationFor('mail') === 'new.address@example.com',
        );
        Notification::assertSentOnDemand(
            EmailChangedNotification::class,
            fn (EmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $channels === ['mail']
                && $notifiable->routes['mail'] === 'member@example.com',
        );

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    }

    public function test_email_change_delivers_verification_to_new_address_and_notice_to_old_address()
    {
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => '  New.Address@Example.COM ',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        // Real mail channel + array transport: recipients are captured at send time,
        // unlike Notification::fake(), which keeps a live reference to the notifiable.
        /** @var ArrayTransport $transport */
        $transport = Mail::mailer('array')->getSymfonyTransport();

        $recipients = $transport->messages()
            ->map(fn (SentMessage $message) => [
                'to' => array_map(fn (Address $address) => $address->getAddress(), $message->getEnvelope()->getRecipients()),
                'subject' => $message->getOriginalMessage() instanceof Email ? $message->getOriginalMessage()->getSubject() : null,
            ])
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing([
            ['to' => ['new.address@example.com'], 'subject' => 'Подтвердите электронную почту'],
            ['to' => ['member@example.com'], 'subject' => 'Электронная почта аккаунта изменена'],
        ], $recipients);
    }

    public function test_unverified_user_can_change_email_with_current_password()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create(['email' => 'mistyped@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => ' Correct.Address@Example.COM ',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('correct.address@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            fn (VerifyEmail $notification, array $channels, User $notifiable) => $channels === ['mail']
                && $notifiable->routeNotificationFor('mail') === 'correct.address@example.com',
        );
        Notification::assertSentOnDemand(
            EmailChangedNotification::class,
            fn (EmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable) => $channels === ['mail']
                && $notifiable->routes['mail'] === 'mistyped@example.com',
        );
    }

    public function test_email_change_to_an_address_taken_in_another_case_is_rejected()
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'TAKEN@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame('member@example.com', $user->refresh()->email);
    }

    public function test_email_changes_are_rate_limited_and_send_no_mail_beyond_the_limit()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)
                ->patch(route('profile.update'), [
                    'name' => $user->name,
                    'email' => $attempt % 2 === 0 ? 'first@example.com' : 'second@example.com',
                    'current_password' => 'password',
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('profile.edit'));
        }

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'third@example.com',
                'current_password' => 'password',
            ])
            ->assertTooManyRequests();

        $this->assertSame('second@example.com', $user->refresh()->email);
        Notification::assertSentToTimes($user, VerifyEmail::class, 6);
        Notification::assertSentTimes(EmailChangedNotification::class, 6);
    }

    public function test_name_only_updates_within_the_limit_work_and_further_requests_are_rejected()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->actingAs($user)
                ->patch(route('profile.update'), [
                    'name' => "Имя {$attempt}",
                    'email' => 'member@example.com',
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('profile.edit'));
        }

        $this->assertSame('Имя 6', $user->refresh()->name);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'attacker@example.com',
                'current_password' => 'password',
            ])
            ->assertTooManyRequests();

        $this->assertSame('member@example.com', $user->refresh()->email);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_rate_limited_inertia_profile_update_shows_russian_error_page()
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html, application/xhtml+xml',
            ])
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertTooManyRequests()
            ->assertSee('Слишком много запросов');
    }

    public function test_verification_link_for_the_previous_email_is_rejected_after_email_change()
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new-address@example.com',
                'current_password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $staleLink = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('member@example.com')],
        );

        $this->actingAs($user->refresh())->get($staleLink)->assertForbidden();

        $this->assertFalse($user->refresh()->hasVerifiedEmail());
    }

    public function test_profile_update_ignores_verification_and_password_fields_in_payload()
    {
        $user = User::factory()->create(['email' => 'member@example.com']);
        $verifiedAt = $user->email_verified_at;

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Новое имя',
                'email' => 'member@example.com',
                'email_verified_at' => null,
                'password' => 'attacker-password',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Новое имя', $user->name);
        $this->assertEquals($verifiedAt, $user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_unverified_user_cannot_mark_email_verified_through_profile_update()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_passwordless_user_must_set_a_password_before_changing_email_or_deleting_account(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new@example.com',
            ])
            ->assertSessionHasErrors(['email', 'current_password'])
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [])
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertSame($user->email, $user->refresh()->email);
        $this->assertNotNull($user->fresh());
    }
}
