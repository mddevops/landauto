<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = $request->input(Fortify::username());
            $email = is_string($email) ? Str::lower($email) : '';
            $ip = $request->ip() ?? 'unknown';
            $throttleKey = Str::transliterate($email.'|'.$ip);

            return [
                Limit::perMinute(5)->by($throttleKey),
                Limit::perMinute(20)->by('ip:'.$ip),
            ];
        });

        RateLimiter::for('yandex-oauth', fn (Request $request) => Limit::perMinute(10)->by($request->ip() ?? 'unknown'));

        foreach (['registration', 'password-email', 'password-reset'] as $limiter) {
            RateLimiter::for($limiter, fn (Request $request) => $this->authLimit($request->ip() ?? 'unknown'));
        }

        foreach (['password-confirm', 'account-delete'] as $limiter) {
            RateLimiter::for($limiter, fn (Request $request) => $this->authLimit(
                (string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown'),
            ));
        }
    }

    private function authLimit(string $key): Limit
    {
        return Limit::perMinute(5)
            ->by($key)
            ->response(fn () => response(__('Too Many Requests'), 429));
    }
}
