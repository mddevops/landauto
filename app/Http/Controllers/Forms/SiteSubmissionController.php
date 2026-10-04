<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Submission;
use App\Support\DesignerScope;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only lead list for a Site (FORMS_AND_INTEGRATIONS.md §31). Shows the stored snapshot,
 * never the visitor IP or user agent.
 */
class SiteSubmissionController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(private DesignerScope $scope) {}

    public function index(Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('viewSubmissions', $site);

        $submissions = $site->submissions()
            ->with('form:id,public_id,name')
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('sites/submissions/index', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'submissions' => array_values($submissions->getCollection()->map(fn (Submission $submission): array => [
                'public_id' => $submission->public_id,
                'form' => ['public_id' => $submission->form->public_id, 'name' => $submission->form->name],
                'status' => $submission->status->label(),
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'phone_normalized' => $submission->phone_normalized,
                'values' => $submission->payload,
                'context' => $submission->context ?? ['trusted' => (object) [], 'visitor' => (object) []],
            ])->all()),
            'pagination' => [
                'current' => $submissions->currentPage(),
                'last' => $submissions->lastPage(),
                'total' => $submissions->total(),
                'prev' => $submissions->previousPageUrl(),
                'next' => $submissions->nextPageUrl(),
            ],
        ]);
    }
}
