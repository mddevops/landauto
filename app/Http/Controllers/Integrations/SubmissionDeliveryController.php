<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\DeliveryOutcome;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrigger;
use App\Http\Controllers\Controller;
use App\Integrations\Delivery\DeliveryScheduler;
use App\Models\Site;
use App\Models\SubmissionDelivery;
use App\Models\SubmissionDeliveryAttempt;
use App\Support\DesignerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Delivery log of a Site's leads (FORMS_AND_INTEGRATIONS.md §28, §31): status, attempts and safe
 * errors per Submission + route, gated by `view_delivery_logs`. It shows no lead field values,
 * provider bodies or credentials. Deliveries are always resolved through a Submission of this
 * Site; a foreign public ID is a 404.
 */
class SubmissionDeliveryController extends Controller
{
    public const PER_PAGE = 25;

    public function __construct(private DesignerScope $scope) {}

    public function index(Request $request, Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('viewDeliveryLogs', $site);

        $status = DeliveryStatus::tryFrom((string) $request->query('status'));
        $deliveries = SubmissionDelivery::query()
            ->whereHas('submission', fn (Builder $query) => $query->where('site_id', $site->id))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status?->value))
            ->with(['submission:id,public_id,form_id,submitted_at', 'submission.form:id,public_id,name', 'route:id,public_id,name', 'attempts'])
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('sites/deliveries/index', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'status' => $status?->value,
            'statuses' => array_map(fn (DeliveryStatus $case): array => ['value' => $case->value, 'label' => $case->label()], DeliveryStatus::cases()),
            'can' => ['retry' => Gate::allows('retryDeliveries', $site)],
            'deliveries' => array_values($deliveries->getCollection()->map(fn (SubmissionDelivery $delivery): array => [
                'public_id' => $delivery->public_id,
                'submission' => [
                    'public_id' => $delivery->submission->public_id,
                    'submitted_at' => $delivery->submission->submitted_at->toIso8601String(),
                ],
                'form' => $delivery->submission->form->name,
                'route' => $delivery->route->name,
                'destination' => $delivery->destination_type->label(),
                'status' => $delivery->status->value,
                'status_label' => $delivery->status->label(),
                'attempt_count' => $delivery->attempt_count,
                'last_attempt_at' => $delivery->last_attempt_at?->toIso8601String(),
                'next_retry_at' => $delivery->next_retry_at?->toIso8601String(),
                'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                'http_status' => $delivery->last_http_status,
                'error' => $delivery->last_error_message_safe,
                'attempts' => array_values($delivery->attempts->map(fn (SubmissionDeliveryAttempt $attempt): array => [
                    'number' => $attempt->attempt_number,
                    'manual' => $attempt->trigger === DeliveryTrigger::Manual,
                    'started_at' => $attempt->started_at->toIso8601String(),
                    'succeeded' => $attempt->status === DeliveryOutcome::Success,
                    'http_status' => $attempt->http_status,
                    'latency_ms' => $attempt->latency_ms,
                    'error' => $attempt->safe_error_message,
                ])->all()),
            ])->all()),
            'pagination' => [
                'current' => $deliveries->currentPage(),
                'last' => $deliveries->lastPage(),
                'total' => $deliveries->total(),
                'prev' => $deliveries->previousPageUrl(),
                'next' => $deliveries->nextPageUrl(),
            ],
        ]);
    }

    public function retry(Site $site, string $delivery, DeliveryScheduler $scheduler): RedirectResponse
    {
        $model = $this->find($site, $delivery);
        Gate::authorize('retryDeliveries', $site);

        $retried = $scheduler->retryNow($model);

        Inertia::flash('toast', $retried
            ? ['type' => 'success', 'message' => 'Повторная отправка запущена.']
            : ['type' => 'error', 'message' => 'Повторить можно только доставку с ошибкой.']);

        return back();
    }

    private function find(Site $site, string $publicId): SubmissionDelivery
    {
        $this->scope->site($site);

        return SubmissionDelivery::query()
            ->where('public_id', $publicId)
            ->whereHas('submission', fn (Builder $query) => $query->where('site_id', $site->id))
            ->firstOrFail();
    }
}
