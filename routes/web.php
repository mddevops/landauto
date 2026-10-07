<?php

use App\Catalog\CatalogLevel;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Auth\YandexOAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Developer\DeveloperDashboardController;
use App\Http\Controllers\Domains\SiteDomainController;
use App\Http\Controllers\Forms\FormFieldController;
use App\Http\Controllers\Forms\FormRouteController;
use App\Http\Controllers\Forms\PreviewSubmissionController;
use App\Http\Controllers\Forms\SiteBlacklistController;
use App\Http\Controllers\Forms\SiteFormController;
use App\Http\Controllers\Forms\SiteFormSecurityController;
use App\Http\Controllers\Forms\SiteSubmissionController;
use App\Http\Controllers\Integrations\IntegrationProfileController;
use App\Http\Controllers\Integrations\IntegrationTestConnectionController;
use App\Http\Controllers\Integrations\SiteAnalyticsController;
use App\Http\Controllers\Integrations\SiteIntegrationController;
use App\Http\Controllers\Integrations\SubmissionDeliveryController;
use App\Http\Controllers\PageBlockController;
use App\Http\Controllers\PageSeoController;
use App\Http\Controllers\Platform\CatalogBrowserController;
use App\Http\Controllers\Platform\CatalogDictionaryController;
use App\Http\Controllers\Platform\CatalogEntryController;
use App\Http\Controllers\Platform\CatalogEquipmentController;
use App\Http\Controllers\Platform\DeveloperProfileController;
use App\Http\Controllers\Platform\SeriesMediaController;
use App\Http\Controllers\Platform\SeriesMediaImageController;
use App\Http\Controllers\Popups\SitePopupController;
use App\Http\Controllers\SiteAssetController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteDesignController;
use App\Http\Controllers\SiteDesignerController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\SitePreviewController;
use App\Http\Controllers\SitePublishingController;
use App\Http\Controllers\SiteSeoController;
use App\Http\Controllers\SiteSubdomainController;
use App\Http\Controllers\SiteVersionRestoreController;
use App\Http\Controllers\Team\InvitationAcceptanceController;
use App\Http\Controllers\Team\WorkspaceInvitationController;
use App\Http\Controllers\Team\WorkspaceMemberController;
use App\Http\Controllers\Team\WorkspaceTeamController;
use App\Http\Controllers\Vehicles\SiteOfferController;
use App\Http\Controllers\Vehicles\SiteVehicleController;
use App\Http\Controllers\Vehicles\SiteVehicleImportController;
use App\Http\Controllers\Vehicles\WorkspaceVehicleController;
use App\Http\Controllers\WorkspaceAssetController;
use App\Http\Controllers\WorkspaceContextController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceSettingsController;
use App\Http\Middleware\EnsureActiveDeveloperProfile;
use App\Http\Middleware\EnsurePlatformPermission;
use App\Http\Middleware\EnsureSiteAccess;
use App\Http\Middleware\RequireWorkspaceContext;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Not a static file in public/: the web server would serve it on published hosts as well.
Route::get('robots.txt', fn () => response("User-agent: *\nDisallow:\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']))
    ->withoutMiddleware('web')
    ->name('robots');

Route::middleware(['guest', 'throttle:yandex-oauth'])->group(function () {
    Route::get('auth/yandex/redirect', [YandexOAuthController::class, 'redirect'])
        ->name('auth.yandex.redirect');
    Route::get('auth/yandex/callback', [YandexOAuthController::class, 'callback'])
        ->name('auth.yandex.callback');
});

Route::middleware('throttle:30,1')->group(function () {
    Route::get('invitations/{token}', [InvitationAcceptanceController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{1,128}')
        ->name('invitations.show');
    Route::get('invitation', [InvitationAcceptanceController::class, 'pending'])->name('invitations.pending');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('invitation/accept', [InvitationAcceptanceController::class, 'accept'])
        ->middleware('throttle:10,1')
        ->name('invitations.accept');

    Route::get('dashboard', DashboardController::class)
        ->middleware(RequireWorkspaceContext::class)
        ->name('dashboard');

    Route::post('workspaces/{workspace}/switch', [WorkspaceContextController::class, 'update'])
        ->whereUlid('workspace')
        ->name('workspace.switch');

    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('workspaces.store');

    Route::middleware(RequireWorkspaceContext::class)->group(function () {
        Route::get('workspace/settings', [WorkspaceSettingsController::class, 'edit'])->name('workspace.settings.edit');
        Route::patch('workspace/settings', [WorkspaceSettingsController::class, 'update'])->name('workspace.settings.update');

        Route::prefix('workspace/vehicles')->name('workspace.vehicles.')->group(function () {
            Route::get('/', [WorkspaceVehicleController::class, 'index'])->name('index');
            Route::get('create', [WorkspaceVehicleController::class, 'create'])->name('create');
            Route::post('/', [WorkspaceVehicleController::class, 'store'])->name('store');
            Route::get('{vehicle}', [WorkspaceVehicleController::class, 'show'])->whereUlid('vehicle')->name('show');
            Route::patch('{vehicle}', [WorkspaceVehicleController::class, 'update'])->whereUlid('vehicle')->name('update');
            Route::put('{vehicle}/media', [WorkspaceVehicleController::class, 'media'])->whereUlid('vehicle')->name('media');
            Route::delete('{vehicle}', [WorkspaceVehicleController::class, 'destroy'])->whereUlid('vehicle')->name('destroy');
            Route::post('{vehicle}/sites', [WorkspaceVehicleController::class, 'copyToSite'])->whereUlid('vehicle')->name('copy');
        });

        Route::prefix('workspace/assets')->name('workspace.assets.')->group(function () {
            Route::get('/', [WorkspaceAssetController::class, 'index'])->name('index');
            Route::post('/', [WorkspaceAssetController::class, 'store'])->middleware('throttle:60,1')->name('store');
            Route::get('{asset}', [WorkspaceAssetController::class, 'show'])->whereUlid('asset')->name('show');
            Route::delete('{asset}', [WorkspaceAssetController::class, 'destroy'])->whereUlid('asset')->name('destroy');
            Route::post('{asset}/sites', [WorkspaceAssetController::class, 'copyToSite'])->whereUlid('asset')->middleware('throttle:60,1')->name('copy');
        });

        Route::prefix('workspace/team')->name('workspace.team.')->group(function () {
            Route::get('/', [WorkspaceTeamController::class, 'index'])->name('index');
            Route::post('invitations', [WorkspaceInvitationController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('invitations.store');
            Route::post('invitations/{invitation}/resend', [WorkspaceInvitationController::class, 'resend'])
                ->whereUlid('invitation')
                ->middleware('throttle:20,1')
                ->name('invitations.resend');
            Route::delete('invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])
                ->whereUlid('invitation')
                ->name('invitations.destroy');
            Route::post('members/{member}/suspend', [WorkspaceMemberController::class, 'suspend'])
                ->whereUlid('member')
                ->name('members.suspend');
            Route::post('members/{member}/reactivate', [WorkspaceMemberController::class, 'reactivate'])
                ->whereUlid('member')
                ->name('members.reactivate');
            Route::put('members/{member}/role', [WorkspaceMemberController::class, 'updateRole'])
                ->whereUlid('member')
                ->name('members.role');
            Route::put('members/{member}/site-access', [WorkspaceMemberController::class, 'updateSiteAccess'])
                ->whereUlid('member')
                ->name('members.site-access');
            Route::delete('members/{member}', [WorkspaceMemberController::class, 'destroy'])
                ->whereUlid('member')
                ->name('members.destroy');
        });
    });

    Route::get('sites/create', [SiteController::class, 'create'])
        ->middleware(RequireWorkspaceContext::class)
        ->name('sites.create');

    Route::post('sites', [SiteController::class, 'store'])
        ->middleware(RequireWorkspaceContext::class)
        ->name('sites.store');

    Route::middleware(RequireWorkspaceContext::class)
        ->prefix('integrations')
        ->name('integrations.')
        ->group(function () {
            Route::get('/', [IntegrationProfileController::class, 'index'])->name('index');
            Route::post('/', [IntegrationProfileController::class, 'store'])->name('store');
            Route::patch('{profile}', [IntegrationProfileController::class, 'update'])->whereUlid('profile')->name('update');
            Route::delete('{profile}', [IntegrationProfileController::class, 'destroy'])->whereUlid('profile')->name('destroy');
            Route::post('{profile}/test', IntegrationTestConnectionController::class)->whereUlid('profile')->name('test');
        });

    Route::middleware([RequireWorkspaceContext::class, EnsureSiteAccess::class])
        ->prefix('sites/{site}')
        ->whereUlid(['site', 'page'])
        ->name('sites.')
        ->group(function () {
            Route::get('/', [SiteController::class, 'show'])->name('show');
            Route::patch('/', [SiteController::class, 'update'])->name('update');
            Route::get('designer', SiteDesignerController::class)->name('designer');
            Route::get('preview', SitePreviewController::class)->name('preview');
            Route::get('publishing', [SitePublishingController::class, 'show'])->name('publishing.show');
            Route::post('publishing', [SitePublishingController::class, 'store'])->middleware('throttle:10,1')->name('publishing.store');
            Route::put('subdomain', [SiteSubdomainController::class, 'update'])->name('subdomain.update');
            Route::get('domains', [SiteDomainController::class, 'index'])->name('domains.index');
            Route::post('domains', [SiteDomainController::class, 'store'])->middleware('throttle:20,1')->name('domains.store');
            Route::delete('domains/{domain}', [SiteDomainController::class, 'destroy'])->whereUlid('domain')->name('domains.destroy');
            Route::post('domains/{domain}/check', [SiteDomainController::class, 'check'])->whereUlid('domain')->middleware('throttle:domain-checks')->name('domains.check');
            Route::post('domains/{domain}/primary', [SiteDomainController::class, 'makePrimary'])->whereUlid('domain')->name('domains.primary');
            Route::delete('domains/primary', [SiteDomainController::class, 'resetPrimary'])->name('domains.reset-primary');
            Route::post('domains/{domain}/ssl', [SiteDomainController::class, 'provisionSsl'])->whereUlid('domain')->middleware('throttle:domain-ssl')->name('domains.ssl');
            Route::post('versions/{version}/restore', SiteVersionRestoreController::class)->whereUlid('version')->middleware('throttle:10,1')->name('versions.restore');
            Route::post('preview/forms/{form}/submissions', [PreviewSubmissionController::class, 'store'])
                ->whereUlid('form')
                ->middleware('throttle:form-submissions')
                ->name('preview.submissions.store');
            Route::patch('design', SiteDesignController::class)->name('design.update');
            Route::post('assets', [SiteAssetController::class, 'store'])->middleware('throttle:60,1')->name('assets.store');
            Route::get('assets/{asset}', [SiteAssetController::class, 'show'])->whereUlid('asset')->name('assets.show');

            Route::post('pages', [SitePageController::class, 'store'])->name('pages.store');
            Route::patch('pages/{page}', [SitePageController::class, 'update'])->name('pages.update');
            Route::delete('pages/{page}', [SitePageController::class, 'destroy'])->name('pages.destroy');
            Route::patch('pages/{page}/seo', [PageSeoController::class, 'update'])->name('pages.seo.update');
            Route::get('seo', SiteSeoController::class)->name('seo.index');

            Route::post('pages/{page}/blocks', [PageBlockController::class, 'store'])->name('blocks.store');
            Route::patch('blocks/{block}/state', [PageBlockController::class, 'state'])->whereUlid('block')->name('blocks.state');
            Route::post('blocks/{block}/move', [PageBlockController::class, 'move'])->whereUlid('block')->name('blocks.move');
            Route::post('blocks/{block}/duplicate', [PageBlockController::class, 'duplicate'])->whereUlid('block')->name('blocks.duplicate');
            Route::patch('blocks/{block}/visibility', [PageBlockController::class, 'visibility'])->whereUlid('block')->name('blocks.visibility');
            Route::delete('blocks/{block}', [PageBlockController::class, 'destroy'])->whereUlid('block')->name('blocks.destroy');

            Route::get('forms', [SiteFormController::class, 'index'])->name('forms.index');
            Route::post('forms', [SiteFormController::class, 'store'])->name('forms.store');
            Route::get('forms/{form}', [SiteFormController::class, 'show'])->whereUlid('form')->name('forms.show');
            Route::patch('forms/{form}', [SiteFormController::class, 'update'])->whereUlid('form')->name('forms.update');
            Route::post('forms/{form}/fields', [FormFieldController::class, 'store'])->whereUlid('form')->name('forms.fields.store');
            Route::put('forms/{form}/fields/order', [FormFieldController::class, 'order'])->whereUlid('form')->name('forms.fields.order');
            Route::patch('forms/{form}/fields/{field}', [FormFieldController::class, 'update'])->whereUlid('form')->where('field', '[a-z][a-z0-9_]{0,39}')->name('forms.fields.update');
            Route::delete('forms/{form}/fields/{field}', [FormFieldController::class, 'destroy'])->whereUlid('form')->where('field', '[a-z][a-z0-9_]{0,39}')->name('forms.fields.destroy');
            Route::get('forms/{form}/routes', [FormRouteController::class, 'index'])->whereUlid('form')->name('forms.routes.index');
            Route::post('forms/{form}/routes', [FormRouteController::class, 'store'])->whereUlid('form')->name('forms.routes.store');
            Route::patch('forms/{form}/routes/{route}', [FormRouteController::class, 'update'])->whereUlid(['form', 'route'])->name('forms.routes.update');
            Route::delete('forms/{form}/routes/{route}', [FormRouteController::class, 'destroy'])->whereUlid(['form', 'route'])->name('forms.routes.destroy');

            Route::get('popups', [SitePopupController::class, 'index'])->name('popups.index');
            Route::post('popups', [SitePopupController::class, 'store'])->name('popups.store');
            Route::patch('popups/{popup}', [SitePopupController::class, 'update'])->whereUlid('popup')->name('popups.update');
            Route::delete('popups/{popup}', [SitePopupController::class, 'destroy'])->whereUlid('popup')->name('popups.destroy');

            Route::get('submissions', [SiteSubmissionController::class, 'index'])->name('submissions.index');
            Route::get('deliveries', [SubmissionDeliveryController::class, 'index'])->name('deliveries.index');
            Route::post('deliveries/{delivery}/retry', [SubmissionDeliveryController::class, 'retry'])->whereUlid('delivery')->middleware('throttle:30,1')->name('deliveries.retry');
            Route::get('form-security', [SiteFormSecurityController::class, 'show'])->name('form-security.show');
            Route::put('form-security', [SiteFormSecurityController::class, 'update'])->name('form-security.update');
            Route::post('blacklist', [SiteBlacklistController::class, 'store'])->name('blacklist.store');
            Route::delete('blacklist/{entry}', [SiteBlacklistController::class, 'destroy'])->whereUlid('entry')->name('blacklist.destroy');

            Route::get('integrations', [SiteIntegrationController::class, 'index'])->name('integrations.index');
            Route::post('integrations', [SiteIntegrationController::class, 'store'])->name('integrations.store');
            Route::patch('integrations/{binding}', [SiteIntegrationController::class, 'update'])->whereUlid('binding')->name('integrations.update');
            Route::delete('integrations/{binding}', [SiteIntegrationController::class, 'destroy'])->whereUlid('binding')->name('integrations.destroy');
            Route::put('analytics', [SiteAnalyticsController::class, 'update'])->name('analytics.update');

            Route::get('vehicles', [SiteVehicleController::class, 'index'])->name('vehicles.index');
            Route::get('vehicles/create', [SiteVehicleController::class, 'create'])->name('vehicles.create');
            Route::get('vehicles/import', [SiteVehicleImportController::class, 'create'])->name('vehicles.imports.create');
            Route::post('vehicles/import', [SiteVehicleImportController::class, 'store'])->middleware('throttle:20,1')->name('vehicles.imports.store');
            Route::post('vehicles', [SiteVehicleController::class, 'store'])->name('vehicles.store');
            Route::get('vehicles/{vehicle}', [SiteVehicleController::class, 'show'])->whereUlid('vehicle')->name('vehicles.show');
            Route::patch('vehicles/{vehicle}', [SiteVehicleController::class, 'update'])->whereUlid('vehicle')->name('vehicles.update');
            Route::put('vehicles/{vehicle}/media', [SiteVehicleController::class, 'media'])->whereUlid('vehicle')->name('vehicles.media');
            Route::delete('vehicles/{vehicle}', [SiteVehicleController::class, 'destroy'])->whereUlid('vehicle')->name('vehicles.destroy');
            Route::post('vehicles/{vehicle}/library', [SiteVehicleController::class, 'saveToLibrary'])->whereUlid('vehicle')->name('vehicles.library');
            Route::post('vehicles/{vehicle}/offers', [SiteOfferController::class, 'store'])->whereUlid('vehicle')->name('offers.store');
            Route::patch('offers/{offer}', [SiteOfferController::class, 'update'])->whereUlid('offer')->name('offers.update');
            Route::delete('offers/{offer}', [SiteOfferController::class, 'destroy'])->whereUlid('offer')->name('offers.destroy');
        });

    Route::get('media/series/{image}', [SeriesMediaImageController::class, 'show'])
        ->whereUlid('image')
        ->name('catalog-media.show');

    // Platform surface: explicit platform permissions only, never Workspace roles (D-058).
    Route::prefix('platform/catalog')
        ->name('platform.catalog.')
        ->middleware(EnsurePlatformPermission::class.':'.PlatformPermission::ViewCatalog->value)
        ->group(function () {
            $levels = array_column(CatalogLevel::cases(), 'value');
            $dictionaries = ['characteristics', 'options'];

            Route::get('/', CatalogBrowserController::class)->name('index');
            Route::get('equipments/{equipment}', [CatalogEquipmentController::class, 'show'])->whereUlid('equipment')->name('equipments.show');
            Route::get('dictionaries/{dictionary}', [CatalogDictionaryController::class, 'show'])->whereIn('dictionary', $dictionaries)->name('dictionaries.show');
            Route::get('series/{series}/media', [SeriesMediaController::class, 'show'])->whereUlid('series')->name('media.show');

            Route::middleware(EnsurePlatformPermission::class.':'.PlatformPermission::EditCatalog->value)->group(function () use ($levels, $dictionaries) {
                Route::post('entries/{level}', [CatalogEntryController::class, 'store'])->whereIn('level', $levels)->name('entries.store');
                Route::patch('entries/{level}/{entry}', [CatalogEntryController::class, 'update'])->whereIn('level', $levels)->whereUlid('entry')->name('entries.update');
                Route::put('equipments/{equipment}/characteristics', [CatalogEquipmentController::class, 'characteristics'])->whereUlid('equipment')->name('equipments.characteristics');
                Route::put('equipments/{equipment}/options', [CatalogEquipmentController::class, 'options'])->whereUlid('equipment')->name('equipments.options');
                Route::post('dictionaries/{dictionary}', [CatalogDictionaryController::class, 'store'])->whereIn('dictionary', $dictionaries)->name('dictionaries.store');
                Route::patch('dictionaries/{dictionary}/{entry}', [CatalogDictionaryController::class, 'update'])->whereIn('dictionary', $dictionaries)->whereUlid('entry')->name('dictionaries.update');
            });

            Route::middleware(EnsurePlatformPermission::class.':'.PlatformPermission::ManageCatalogMedia->value)->group(function () {
                Route::post('series/{series}/media-sets', [SeriesMediaController::class, 'store'])->whereUlid('series')->name('media.sets.store');
                Route::patch('media-sets/{set}', [SeriesMediaController::class, 'update'])->whereUlid('set')->name('media.sets.update');
                Route::post('media-sets/{set}/images', [SeriesMediaImageController::class, 'store'])
                    ->whereUlid('set')
                    ->middleware('throttle:60,1')
                    ->name('media.images.store');
                Route::delete('media-images/{image}', [SeriesMediaImageController::class, 'destroy'])
                    ->whereUlid('image')
                    ->name('media.images.destroy');
            });
        });

    Route::prefix('platform/developers')
        ->name('platform.developers.')
        ->middleware(EnsurePlatformPermission::class.':'.PlatformPermission::ManageDevelopers->value)
        ->group(function () {
            Route::get('/', [DeveloperProfileController::class, 'index'])->name('index');
            Route::post('/', [DeveloperProfileController::class, 'store'])->middleware('throttle:30,1')->name('store');
            Route::post('{developer}/suspend', [DeveloperProfileController::class, 'suspend'])->whereUlid('developer')->name('suspend');
            Route::post('{developer}/reactivate', [DeveloperProfileController::class, 'reactivate'])->whereUlid('developer')->name('reactivate');
            Route::put('{developer}/permissions', [DeveloperProfileController::class, 'updatePermissions'])->whereUlid('developer')->name('permissions.update');
        });

    // Developer Platform: the current User's own active Developer Profile, never Workspace context (D-093).
    Route::get('developer', DeveloperDashboardController::class)
        ->middleware(EnsureActiveDeveloperProfile::class)
        ->name('developer.dashboard');
});

require __DIR__.'/settings.php';
