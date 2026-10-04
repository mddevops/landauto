<?php

namespace App\Providers;

use App\Enums\PlatformPermission;
use App\Enums\WorkspacePermission;
use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;
use App\Support\PlatformAuthorization;
use App\Support\WorkspaceAuthorization;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
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
