<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Integrations\Delivery\DeliveryScheduler;
use App\Models\Site;
use App\Models\SubmissionDelivery;
use App\Support\DesignerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Delivery status of a Site's leads (FORMS_AND_INTEGRATIONS.md §28, §31). Deliveries are always
 * resolved through a Submission of this Site; a foreign public ID is a 404.
 */
class SubmissionDeliveryController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

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
