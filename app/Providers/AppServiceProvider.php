<?php

namespace App\Providers;

use App\Domains\Dns\DnsResolver;
use App\Domains\Dns\FakeDnsResolver;
use App\Domains\Dns\SystemDnsResolver;
use App\Domains\Ssl\CommandSslProvisioner;
use App\Domains\Ssl\FakeSslProvisioner;
use App\Domains\Ssl\NoneSslProvisioner;
use App\Domains\Ssl\SslProvisioner;
use App\Enums\PlatformPermission;
use App\Enums\WorkspacePermission;
use App\Forms\Captcha\CaptchaVerifier;
use App\Forms\Captcha\FakeCaptchaVerifier;
use App\Forms\Captcha\YandexSmartCaptchaVerifier;
use App\Integrations\Delivery\DeliveryAdapters;
use App\Integrations\Delivery\EmailDeliveryAdapter;
use App\Integrations\Delivery\HttpDeliveryAdapter;
use App\Integrations\Http\HostResolver;
use App\Integrations\Http\SystemHostResolver;
use App\Integrations\Testing\E2eIntegrationFakes;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\User;
use App\Policies\SitePolicy;
use App\Publishing\Rendering\NodePageRenderer;
use App\Publishing\Rendering\PageRenderer;
use App\Support\PlatformAuthorization;
use App\Support\SiteAccessResolver;
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
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkspaceContext::class);
        $this->app->scoped(WorkspaceAuthorization::class);
        $this->app->scoped(SiteAccessResolver::class);
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
        $this->app->bind(HostResolver::class, $this->usesE2eIntegrationFakes() ? E2eIntegrationFakes::class : SystemHostResolver::class);
        $this->app->bind(DnsResolver::class, $this->usesFakeDomainDriver('dns_driver') ? FakeDnsResolver::class : SystemDnsResolver::class);
        $this->app->bind(SslProvisioner::class, match (true) {
            $this->usesFakeDomainDriver('ssl_driver') => FakeSslProvisioner::class,
            config('domains.ssl_driver') === 'command' => CommandSslProvisioner::class,
            default => NoneSslProvisioner::class,
        });
        $this->app->tag([EmailDeliveryAdapter::class, HttpDeliveryAdapter::class], DeliveryAdapters::TAG);
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

        if ($this->usesE2eIntegrationFakes()) {
            E2eIntegrationFakes::install();
        }

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

        RateLimiter::for('domain-checks', function (Request $request) {
            $site = $request->route('site');

            return Limit::perMinute(max(1, (int) config('domains.check_per_minute')))
                ->by('domain-checks:'.match (true) {
                    $site instanceof Site => $site->public_id,
                    is_string($site) => $site,
                    default => $request->ip() ?? 'unknown',
                })
                ->response(function () {
                    Inertia::flash('toast', ['type' => 'error', 'message' => 'Слишком частые проверки. Подождите минуту и попробуйте снова.']);

                    return back(303);
                });
        });

        // Manual certificate retries per domain; each failed ACME validation counts against the CA's per-hostname limit.
        RateLimiter::for('domain-ssl', function (Request $request) {
            $domain = $request->route('domain');

            return Limit::perHour(4)
                ->by('domain-ssl:'.match (true) {
                    $domain instanceof SiteDomain => $domain->public_id,
                    is_string($domain) => $domain,
                    default => $request->ip() ?? 'unknown',
                })
                ->response(function () {
                    Inertia::flash('toast', ['type' => 'error', 'message' => 'Слишком много попыток выпуска сертификата. Повторите позже.']);

                    return back(303);
                });
        });

        $this->configureCreatorStudioLimiters();
    }

    /**
     * Creator Studio limiters with semantic keys: numeric `throttle:N,1` shares one counter per User
     * across every such route, so Draft autosaves would exhaust publishing. Block and Template keys
     * use the route ULID string (bound before model resolution); the User is always the server identity.
     */
    private function configureCreatorStudioLimiters(): void
    {
        $actor = fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown');
        $item = fn (Request $request, string $parameter): string => is_string($request->route($parameter)) ? $request->route($parameter) : 'none';

        RateLimiter::for('block-create', fn (Request $request) => Limit::perMinute(30)->by('block-create:'.$actor($request)));
        RateLimiter::for('block-draft', fn (Request $request) => Limit::perMinute(120)->by('block-draft:'.$actor($request).':'.$item($request, 'block')));
        RateLimiter::for('block-publish', fn (Request $request) => Limit::perMinute(30)->by('block-publish:'.$actor($request).':'.$item($request, 'block')));
        RateLimiter::for('template-create', fn (Request $request) => Limit::perMinute(30)->by('template-create:'.$actor($request)));
        RateLimiter::for('template-publish', fn (Request $request) => Limit::perMinute(30)->by('template-publish:'.$actor($request).':'.$item($request, 'template')));
    }

    private function usesE2eIntegrationFakes(): bool
    {
        return config('integrations.e2e_fake') === true && $this->app->environment(['testing', 'e2e']);
    }

    /** Domain fakes (DNS, SSL) are honoured only in the testing and e2e environments. */
    private function usesFakeDomainDriver(string $key): bool
    {
        return config("domains.{$key}") === 'fake' && $this->app->environment(['testing', 'e2e']);
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
