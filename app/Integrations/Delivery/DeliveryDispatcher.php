<?php

namespace App\Integrations\Delivery;

use App\Enums\SubmissionMode;
use App\Jobs\ProcessSubmissionDelivery;
use App\Models\FormRoute;
use App\Models\Submission;
use App\Models\SubmissionDelivery;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates one Delivery per active Form Route for an already persisted public Submission and
 * queues its processing (FORMS_AND_INTEGRATIONS.md §24–25). Preview Submissions are never
 * delivered. A failure here never undoes the stored lead.
 */
final class DeliveryDispatcher
{
    public function dispatchFor(Submission $submission): void
    {
        if ($submission->mode !== SubmissionMode::Public) {
            return;
        }

        try {
            $routes = FormRoute::query()
                ->where('form_id', $submission->form_id)
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($routes as $route) {
                $delivery = new SubmissionDelivery;
                $delivery->forceFill([
                    'submission_id' => $submission->id,
                    'form_route_id' => $route->id,
                    'destination_type' => $route->destination_type,
                ]);

                try {
                    $delivery->save();
                } catch (UniqueConstraintViolationException) {
                    continue;
                }

                ProcessSubmissionDelivery::dispatch($delivery->id)->afterCommit();
            }
        } catch (Throwable $exception) {
            Log::error('delivery.dispatch_failed', [
                'submission' => $submission->public_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
