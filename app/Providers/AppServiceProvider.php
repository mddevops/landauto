<?php

namespace App\Providers;

use App\Enums\PlatformPermission;
use App\Enums\WorkspacePermission;
use App\Forms\Captcha\CaptchaVerifier;
use App\Forms\Captcha\FakeCaptchaVerifier;
use App\Forms\Captcha\YandexSmartCaptchaVerifier;
use App\Integrations\Delivery\DeliveryAdapters;
use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;
use App\Publishing\Rendering\NodePageRenderer;
use App\Publishing\Rendering\PageRenderer;
use App\Support\PlatformAuthorization;
use App\Support\WorkspaceAuthorization;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class);
        $this->app->scoped(WorkspaceAuthorization::class);
        $this->app->bind(PageRenderer::class, NodePageRenderer::class);
        $this->app->singleton(CaptchaVerifier::class, function (Application $app): CaptchaVerifier {
            if (config('forms.captcha_driver') === 'fake' && $app->environment(['testing', 'e2e'])) {
                return new FakeCaptchaVerifier;
            }

            return new YandexSmartCaptchaVerifier(
                $app->make(HttpFactory::class),
                config('services.yandex_smartcaptcha.client_key'),
                config('services.yandex_smartcaptcha.server_key'),
                (int) config('services.yandex_smartcaptcha.timeout'),
            );
        });
        $this->app->singleton(DeliveryAdapters::class, fn (Application $app): DeliveryAdapters => new DeliveryAdapters(
            $app->tagged(DeliveryAdapters::TAG),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::policy(Site::class, SitePolicy::class);

        foreach (WorkspacePermission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user): bool => app(WorkspaceAuthorization::class)->allows($user, $permission),
            );
        }

        foreach (PlatformPermission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user): bool => app(PlatformAuthorization::class)->allows($user, $permission),
            );
        }

        // Coarse per-IP backstop for the public Form endpoint; Site security policy limits apply on top.
        RateLimiter::for('form-submissions', fn (Request $request) => Limit::perMinute(30)
            ->by($request->ip() ?? 'unknown')
            ->response(fn () => response()->json(['message' => 'Слишком много попыток. Попробуйте позже.'], 429)));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
