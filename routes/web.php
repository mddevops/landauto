<?php

use App\Http\Controllers\Auth\YandexOAuthController;
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
    Route::inertia('dashboard', 'dashboard')
        ->middleware(RequireWorkspaceContext::class)
        ->name('dashboard');

    Route::post('workspaces/{workspace}/switch', [WorkspaceContextController::class, 'update'])
        ->whereUlid('workspace')
        ->name('workspace.switch');
});

require __DIR__.'/settings.php';
