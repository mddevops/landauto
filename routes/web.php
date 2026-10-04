<?php

use App\Http\Controllers\Auth\YandexOAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteDesignerController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\WorkspaceContextController;
use App\Http\Middleware\RequireWorkspaceContext;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['guest', 'throttle:yandex-oauth'])->group(function () {
    Route::get('auth/yandex/redirect', [YandexOAuthController::class, 'redirect'])
        ->name('auth.yandex.redirect');
    Route::get('auth/yandex/callback', [YandexOAuthController::class, 'callback'])
        ->name('auth.yandex.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware(RequireWorkspaceContext::class)
        ->name('dashboard');

    Route::post('workspaces/{workspace}/switch', [WorkspaceContextController::class, 'update'])
        ->whereUlid('workspace')
        ->name('workspace.switch');

    Route::get('sites/create', [SiteController::class, 'create'])
        ->middleware(RequireWorkspaceContext::class)
        ->name('sites.create');

    Route::post('sites', [SiteController::class, 'store'])
        ->middleware(RequireWorkspaceContext::class)
        ->name('sites.store');

    Route::middleware(RequireWorkspaceContext::class)
        ->prefix('sites/{site}')
        ->whereUlid(['site', 'page'])
        ->name('sites.')
        ->group(function () {
            Route::get('designer', SiteDesignerController::class)->name('designer');

            Route::post('pages', [SitePageController::class, 'store'])->name('pages.store');
            Route::patch('pages/{page}', [SitePageController::class, 'update'])->name('pages.update');
            Route::delete('pages/{page}', [SitePageController::class, 'destroy'])->name('pages.destroy');
        });
});

require __DIR__.'/settings.php';
