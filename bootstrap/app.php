<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveWorkspaceContext;
use App\Http\Middleware\ValidateFortifyEmail;
use App\Integrations\IntegrationProfiles;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function (): void {
            // Published Site hosts first and without the `web` group, so their catch-all wins
            // over application routes and public hosts never get sessions, cookies or CSRF.
            Route::group([], base_path('routes/public.php'));

            Route::get('/up', function (Request $request) {
                $healthy = true;

                try {
                    Event::dispatch(new DiagnosingHealth);
                } catch (Throwable $exception) {
                    report($exception);
                    $healthy = false;
                }

                return response()->json(['status' => $healthy ? 'up' : 'down'], $healthy ? 200 : 500);
            });

            Route::middleware('web')->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Block Studio Draft sources are code and must be stored exactly as written.
        $middleware->trimStrings(except: ['sources.html', 'sources.css', 'sources.js', 'sources.schema']);

        $middleware->web(append: [
            ValidateFortifyEmail::class,
            ResolveWorkspaceContext::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Integration credentials must never be kept as old input in the session.
        $exceptions->dontFlash(IntegrationProfiles::CREDENTIAL_INPUTS);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
