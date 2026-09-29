<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
