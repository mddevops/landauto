<?php

namespace App\Http\Controllers;

use App\Models\PublishedVersion;
use App\Models\Site;
use App\Publishing\RestoreFailed;
use App\Publishing\RestoreVersion;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Restore-to-Draft (`restore_version`): the version's private snapshot replaces the Draft;
 * production stays as it is until the next explicit Publish creates a new version.
 */
class SiteVersionRestoreController extends Controller
{
    public function __invoke(Request $request, Site $site, PublishedVersion $version, DesignerScope $scope, RestoreVersion $restore): RedirectResponse
    {
        $scope->site($site);
        abort_unless($version->site_id === $site->id, 404);
        Gate::authorize('restoreVersion', $site);

        try {
            $summary = $restore->handle($site, $version, $request->user());
        } catch (RestoreFailed $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('sites.publishing.show', $site);
        }

        $message = "Черновик восстановлен из версии {$version->version_number}. Проверьте предпросмотр и опубликуйте.";

        if ($summary['skipped_vehicles'] > 0 || $summary['skipped_offers'] > 0) {
            $message .= ' Часть автомобилей или предложений больше нет в каталоге, они не восстановлены.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('sites.publishing.show', $site);
    }
}
