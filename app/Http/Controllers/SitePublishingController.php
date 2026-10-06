<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Publishing\PublishedSnapshotBuilder;
use App\Publishing\PublishSite;
use App\Publishing\PublishValidator;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Публикация»: current production version, the last Publish attempt, the live pre-publish
 * check of the Draft, the paginated version history and the explicit Publish action
 * (`publish_site`). Unpublished changes = the Draft's deterministic public manifest hash differs
 * from the active version's hash, i.e. publishing now would change what visitors see.
 */
class SitePublishingController extends Controller
{
    private const HISTORY_PER_PAGE = 20;

    public function __construct(private DesignerScope $scope) {}

    public function show(Request $request, Site $site, PublishValidator $validator, PublishedSnapshotBuilder $snapshots): Response
    {
        $this->scope->site($site);
        Gate::authorize('view', $site);

        $active = $site->activePublishedVersion()->with(['creator', 'publication'])->first();
        $last = $site->publications()->with('actor')->latest('started_at')->latest('id')->first();
        $lastRestore = $site->versionRestores()->with(['actor:id,name', 'version:id,version_number'])->latest('id')->first();

        $versions = $site->publishedVersions()
            ->select(['id', 'public_id', 'site_id', 'version_number', 'status', 'created_by', 'ready_at', 'created_at'])
            ->with(['creator:id,name', 'publication:id,published_version_id,note', 'latestRestore.actor:id,name'])
            ->withCount('restores')
            ->orderByDesc('version_number')
            ->orderByDesc('id')
            ->paginate(self::HISTORY_PER_PAGE, pageName: 'page', page: max(1, $request->integer('page', 1)));

        return Inertia::render('sites/publishing', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'address' => [
                'subdomain' => $site->subdomain,
                'domain' => (string) config('publishing.public_domain'),
                'url' => PublicSiteResolver::url($site),
            ],
            'production' => $active === null ? null : [
                'public_id' => $active->public_id,
                'version_number' => $active->version_number,
                'published_at' => $active->ready_at?->toIso8601String(),
                'publisher' => $active->creator?->name,
                'note' => $active->publication?->note,
                // Unknown (null) when the Draft cannot be snapshotted yet; Publish reports why.
                'has_unpublished_changes' => rescue(fn (): bool => $snapshots->build($site)->manifestHash !== $active->manifest_hash, null, false),
            ],
            'lastAttempt' => $last === null ? null : $this->attempt($last),
            'lastRestore' => $lastRestore === null ? null : [
                'version_number' => $lastRestore->version->version_number,
                'restored_at' => $lastRestore->restored_at->toIso8601String(),
                'actor' => $lastRestore->actor?->name,
            ],
            'versions' => array_map(fn (PublishedVersion $version): array => [
                'public_id' => $version->public_id,
                'version_number' => $version->version_number,
                'status' => $version->status->value,
                'status_label' => $version->status->label(),
                'published_at' => ($version->ready_at ?? $version->created_at)?->toIso8601String(),
                'publisher' => $version->creator?->name,
                'note' => $version->publication?->note,
                'is_production' => $version->id === $site->active_published_version_id,
                'restores_count' => (int) $version->getAttribute('restores_count'),
                'last_restore' => $version->latestRestore === null ? null : [
                    'restored_at' => $version->latestRestore->restored_at->toIso8601String(),
                    'actor' => $version->latestRestore->actor?->name,
                ],
            ], $versions->items()),
            'versionsPage' => [
                'current' => $versions->currentPage(),
                'last' => $versions->lastPage(),
                'total' => $versions->total(),
                'per_page' => $versions->perPage(),
            ],
            'check' => $validator->validate($site)->toArray(),
            'can' => [
                'publish' => Gate::allows('publish', $site),
                'preview' => Gate::allows('preview', $site),
                'manageDomains' => Gate::allows('manageDomains', $site),
                'restoreVersion' => Gate::allows('restoreVersion', $site),
            ],
        ]);
    }

    public function store(Request $request, Site $site, PublishSite $publisher): RedirectResponse
    {
        $this->scope->site($site);
        Gate::authorize('publish', $site);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'note.string' => 'Комментарий должен быть текстом.',
            'note.max' => 'Комментарий не должен быть длиннее 500 символов.',
        ]);

        $outcome = $publisher->handle($site, $request->user(), self::note($validated['note'] ?? null));

        Inertia::flash('toast', $outcome->succeeded()
            ? ['type' => 'success', 'message' => "Сайт опубликован. Версия {$outcome->version?->version_number}."]
            : ['type' => 'error', 'message' => $outcome->publication->safe_error_summary ?? 'Публикация не удалась.']);

        return to_route('sites.publishing.show', $site);
    }

    /**
     * Plain text only: control characters (except line breaks) removed, line endings unified,
     * surrounding whitespace trimmed; an empty note is stored as null. Rendered escaped by React.
     */
    private static function note(mixed $note): ?string
    {
        if (! is_string($note)) {
            return null;
        }

        $note = str_replace(["\r\n", "\r"], "\n", $note);
        $note = trim((string) preg_replace('/[^\P{C}\n]/u', '', $note));

        return $note === '' ? null : $note;
    }

    /**
     * @return array{status: string, status_label: string, started_at: string, actor: string|null, note: string|null, error: string|null}
     */
    private function attempt(Publication $publication): array
    {
        return [
            'status' => $publication->status->value,
            'status_label' => $publication->status->label(),
            'started_at' => $publication->started_at->toIso8601String(),
            'actor' => $publication->actor?->name,
            'note' => $publication->note,
            'error' => $publication->safe_error_summary,
        ];
    }
}
