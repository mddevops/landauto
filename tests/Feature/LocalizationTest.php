<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailChangedNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_locale_is_russian()
    {
        $this->assertSame('ru', app()->getLocale());
    }

    public function test_pages_declare_russian_document_language()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('<html lang="ru"', escape: false);
    }

    public function test_failed_login_error_is_russian()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'Неверная электронная почта или пароль.',
        ]);
    }

    public function test_validation_errors_use_russian_messages_and_attribute_names()
    {
        $response = $this->post(route('register.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'name' => 'Поле «Имя» обязательно для заполнения.',
            'email' => 'Поле «Электронная почта» должно содержать корректный адрес электронной почты.',
        ]);
        $this->assertGuest();
    }

    public function test_profile_update_notification_is_russian()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Тестовый пользователь',
                'email' => $user->email,
            ]);

        $response->assertInertiaFlash('toast.message', 'Профиль обновлён.');
    }

    public function test_verification_email_is_russian()
    {
        $user = User::factory()->unverified()->create();

        $mail = (new VerifyEmail)->toMail($user);

        $this->assertSame('Подтвердите электронную почту', $mail->subject);
        $this->assertSame('Подтвердить электронную почту', $mail->actionText);
        $this->assertSame(['Нажмите кнопку ниже, чтобы подтвердить электронную почту.'], $mail->introLines);
    }

    public function test_email_changed_notice_is_russian_and_has_no_action_link()
    {
        $mail = (new EmailChangedNotification)->toMail(new AnonymousNotifiable);

        $this->assertSame('Электронная почта аккаунта изменена', $mail->subject);
        $this->assertSame([
            'Электронная почта вашего аккаунта '.config('app.name').' изменена. Этот адрес больше не используется для входа.',
            'Если вы не меняли электронную почту, немедленно обратитесь в службу поддержки.',
        ], $mail->introLines);
        $this->assertNull($mail->actionUrl);
    }

    public function test_wrong_current_password_on_email_change_error_is_russian()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new-address@example.com',
                'current_password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors(['current_password' => 'Неверный пароль.']);
    }

    public function test_missing_current_password_on_email_change_error_is_russian()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => 'new-address@example.com',
            ]);

        $response->assertSessionHasErrors([
            'current_password' => 'Поле «Текущий пароль» обязательно для заполнения.',
        ]);
    }
}
