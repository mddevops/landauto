<?php

use App\Http\Controllers\PublicSite\PublishedAssetController;
use App\Http\Controllers\PublicSite\PublishedFormController;
use App\Http\Controllers\PublicSite\PublishedPageController;
use App\Http\Controllers\PublicSite\PublishedRuntimeAssetController;
use App\Http\Controllers\PublicSite\PublishedSeoController;
use App\Http\Middleware\ResolvePublicSite;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Support\SitePublicNumber;
use Illuminate\Support\Facades\Route;

/*
| Published Sites on {public_domain}/{number} (D-127) and at the root of connected custom domains
| (D-111). Content comes only from the active Published Version. Reserved Site paths:
| /_landflow/*, /sitemap.xml, /robots.txt. Anything else that is not a published Page is a safe
| 404, so no application route is reachable through a public address.
*/

$runtime = function (string $name): void {
    Route::middleware(ResolvePublicSite::class)->name($name)->group(function () {
        Route::get('_landflow/assets/{version}/{asset}', [PublishedAssetController::class, 'asset'])
            ->whereUlid(['version', 'asset'])
            ->name('assets');
        Route::get('_landflow/media/{version}/{image}', [PublishedAssetController::class, 'media'])
            ->whereUlid(['version', 'image'])
            ->name('media');
        Route::get('_landflow/runtime/{version}/{hash}.css', [PublishedRuntimeAssetController::class, 'stylesheet'])
            ->whereUlid('version')
            ->where('hash', '[a-f0-9]{64}')
            ->name('runtime.css');
        Route::get('_landflow/runtime/{version}/{hash}.js', [PublishedRuntimeAssetController::class, 'javascript'])
            ->whereUlid('version')->where('hash', '[a-f0-9]{64}')->name('runtime.js');
        Route::post('_landflow/forms/{version}/{form}', PublishedFormController::class)
            ->whereUlid(['version', 'form'])
            ->middleware('throttle:form-submissions')
            ->name('forms');
        Route::get('sitemap.xml', [PublishedSeoController::class, 'sitemap'])->name('sitemap');
        Route::get('robots.txt', [PublishedSeoController::class, 'robots'])->name('robots');

        Route::get('{path?}', PublishedPageController::class)->where('path', '[a-z0-9-]*')->name('page');
        Route::any('{any?}', [PublishedPageController::class, 'missing'])->where('any', '.*')->name('missing');
    });
};

Route::domain((string) config('publishing.public_domain'))->group(function () use ($runtime): void {
    Route::prefix('{publicNumber}')
        ->where(['publicNumber' => SitePublicNumber::PATTERN])
        ->group(fn () => $runtime('public.'));

    // A public domain of its own serves nothing but Sites (the shared-host case exists only locally).
    if (! PublicSiteResolver::sharesApplicationHost()) {
        Route::any('{any?}', [PublishedPageController::class, 'missing'])->where('any', '.*')->name('public.root.missing');
    }
});

Route::domain('{host}')
    ->where(['host' => PublicSiteResolver::customHostPattern()])
    ->group(fn () => $runtime('public.custom.'));
