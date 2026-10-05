<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Site;
use App\Publishing\PublishSite;
use App\Publishing\PublishValidator;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Публикация»: current production version, the last Publish attempt, the live pre-publish
 * check of the Draft and the explicit Publish action (`publish_site`).
 */
class SitePublishingController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function show(Site $site, PublishValidator $validator): Response
    {
        $this->scope->site($site);
        Gate::authorize('view', $site);

        $active = $site->activePublishedVersion()->with('creator')->first();
        $last = $site->publications()->with('actor')->latest('started_at')->latest('id')->first();

        return Inertia::render('sites/publishing', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'production' => $active === null ? null : [
                'public_id' => $active->public_id,
                'version_number' => $active->version_number,
                'published_at' => $active->ready_at?->toIso8601String(),
                'publisher' => $active->creator?->name,
            ],
            'lastAttempt' => $last === null ? null : $this->attempt($last),
            'check' => $validator->validate($site)->toArray(),
            'can' => [
                'publish' => Gate::allows('publish', $site),
                'preview' => Gate::allows('preview', $site),
            ],
        ]);
    }

    public function store(Request $request, Site $site, PublishSite $publisher): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('publish', $site);

        $outcome = $publisher->handle($site, $request->user());

        Inertia::flash('toast', $outcome->succeeded()
            ? ['type' => 'success', 'message' => "Сайт опубликован. Версия {$outcome->version?->version_number}."]
            : ['type' => 'error', 'message' => $outcome->publication->safe_error_summary ?? 'Публикация не удалась.']);

        return to_route('sites.publishing.show', $site);
    }

    /**
     * @return array{status: string, status_label: string, started_at: string, actor: string|null, error: string|null}
     */
    private function attempt(Publication $publication): array
    {
        return [
            'status' => $publication->status->value,
            'status_label' => $publication->status->label(),
            'started_at' => $publication->started_at->toIso8601String(),
            'actor' => $publication->actor?->name,
            'error' => $publication->safe_error_summary,
        ];
    }
}
