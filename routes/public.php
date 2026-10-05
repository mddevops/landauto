<?php

use App\Http\Controllers\PublicSite\PublishedAssetController;
use App\Http\Controllers\PublicSite\PublishedFormController;
use App\Http\Controllers\PublicSite\PublishedPageController;
use App\Http\Middleware\ResolvePublicSite;
use App\Publishing\Runtime\PublicSiteResolver;
use Illuminate\Support\Facades\Route;

/*
| Published Sites on {subdomain}.{public_domain} (ADR-006 §9). Content comes only from the active
| Published Version. Reserved paths: /_landflow/*, /sitemap.xml, /robots.txt. Anything else that is
| not a published Page is a safe 404, so no application route is reachable through a public host.
*/

Route::domain('{subdomain}.'.config('publishing.public_domain'))
    ->where(['subdomain' => PublicSiteResolver::routePattern()])
    ->middleware(ResolvePublicSite::class)
    ->name('public.')
    ->group(function () {
        Route::get('_landflow/assets/{version}/{asset}', [PublishedAssetController::class, 'asset'])
            ->whereUlid(['version', 'asset'])
            ->name('assets');
        Route::get('_landflow/media/{version}/{image}', [PublishedAssetController::class, 'media'])
            ->whereUlid(['version', 'image'])
            ->name('media');
        Route::post('_landflow/forms/{version}/{form}', PublishedFormController::class)
            ->whereUlid(['version', 'form'])
            ->middleware('throttle:form-submissions')
            ->name('forms');

        Route::get('{path?}', PublishedPageController::class)->where('path', '[a-z0-9-]*')->name('page');
        Route::any('{any?}', [PublishedPageController::class, 'missing'])->where('any', '.*')->name('missing');
    });
