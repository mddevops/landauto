<?php

namespace App\Http\Controllers\Forms;

use App\Enums\SubmissionMode;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use App\Support\DesignerScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only lead list for a Site (FORMS_AND_INTEGRATIONS.md §31). Shows the stored snapshot,
 * never the visitor IP or user agent. Preview test entries are never mixed with real leads.
 */
class SiteSubmissionController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(private DesignerScope $scope) {}

    /**
     * Real leads by default; `?mode=preview` lists test entries sent from the draft preview.
     */
    public function index(Request $request, Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('viewSubmissions', $site);

        $mode = $request->query('mode') === SubmissionMode::Preview->value ? SubmissionMode::Preview : SubmissionMode::Public;
        $canViewDeliveries = Gate::allows('viewDeliveryLogs', $site);
        $submissions = $site->submissions()
            ->where('mode', $mode->value)
            ->with('form:id,public_id,name')
            ->when($canViewDeliveries, fn ($query) => $query->with('deliveries.route:id,name'))
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('sites/submissions/index', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'mode' => $mode->value,
            'previewCount' => $site->submissions()->where('mode', SubmissionMode::Preview->value)->count(),
            'submissions' => array_values($submissions->getCollection()->map(fn (Submission $submission): array => [
                'public_id' => $submission->public_id,
                'form' => ['public_id' => $submission->form->public_id, 'name' => $submission->form->name],
                'status' => $submission->status->label(),
                'mode' => $submission->mode->value,
                'mode_label' => $submission->mode->label(),
                'submitted_at' => $submission->submitted_at->toIso8601String(),
                'phone_normalized' => $submission->phone_normalized,
                'values' => $submission->payload,
                'context' => $submission->context ?? ['trusted' => (object) [], 'visitor' => (object) []],
                'deliveries' => $canViewDeliveries ? array_values($submission->deliveries->map(fn (SubmissionDelivery $delivery): array => [
                    'route' => $delivery->route->name,
                    'status' => $delivery->status->value,
                    'status_label' => $delivery->status->label(),
                ])->all()) : [],
            ])->all()),
            'canViewDeliveries' => $canViewDeliveries,
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
