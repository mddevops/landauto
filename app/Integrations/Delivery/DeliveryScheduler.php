<?php

namespace App\Integrations\Delivery;

use App\Enums\DeliveryOutcome;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrigger;
use App\Jobs\ProcessSubmissionDelivery;
use App\Models\SubmissionDelivery;
use App\Models\SubmissionDeliveryAttempt;
use Illuminate\Database\Eloquent\Builder;

/**
 * Time-based side of the retry policy. Retries live on the Delivery row (`next_retry_at`), and a
 * scheduled command queues the due ones, so a lost queue job never loses a lead and the queue
 * never loops. Deliveries left in `processing` by a lost worker are rescheduled.
 */
final class DeliveryScheduler
{
    public const BATCH = 500;

    public const WORKER_LOST = 'worker_lost';

    /**
     * @return array{recovered: int, dispatched: int}
     */
    public function dispatchDue(): array
    {
        $recovered = $this->recoverStale();
        $now = now();
        $stalePending = $now->subMinutes(config()->integer('integrations.stale_processing_minutes'));

        $ids = SubmissionDelivery::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $due) => $due
                    ->where('status', DeliveryStatus::RetryScheduled->value)
                    ->where(fn (Builder $time) => $time->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now)))
                ->orWhere(fn (Builder $lost) => $lost
                    ->where('status', DeliveryStatus::Pending->value)
                    ->where('created_at', '<=', $stalePending)))
            ->orderBy('id')
            ->limit(self::BATCH)
            ->pluck('id');

        foreach ($ids as $id) {
            ProcessSubmissionDelivery::dispatch($id);
        }

        return ['recovered' => $recovered, 'dispatched' => $ids->count()];
    }

    /**
     * Manual retry of a failed Delivery (`retry_deliveries`): a new Attempt on the same Delivery;
     * the Submission itself is never rewritten.
     */
    public function retryNow(SubmissionDelivery $delivery): bool
    {
        $moved = SubmissionDelivery::query()
            ->whereKey($delivery->id)
            ->where('status', DeliveryStatus::Failed->value)
            ->update([
                'status' => DeliveryStatus::RetryScheduled->value,
                'next_retry_at' => null,
                'updated_at' => now(),
            ]) === 1;

        if ($moved) {
            ProcessSubmissionDelivery::dispatch($delivery->id, DeliveryTrigger::Manual);
        }

        return $moved;
    }

    private function recoverStale(): int
    {
        $threshold = now()->subMinutes(config()->integer('integrations.stale_processing_minutes'));
        $recovered = 0;

        $stale = SubmissionDelivery::query()
            ->where('status', DeliveryStatus::Processing->value)
            ->where('last_attempt_at', '<=', $threshold)
            ->limit(self::BATCH)
            ->get();

        foreach ($stale as $delivery) {
            $exhausted = ! array_key_exists($delivery->attempt_count - 1, config()->array('integrations.retry_delays'));
            $message = 'Доставка прервана. Будет выполнена повторная попытка.';

            $moved = SubmissionDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', DeliveryStatus::Processing->value)
                ->update([
                    'status' => $exhausted ? DeliveryStatus::Failed->value : DeliveryStatus::RetryScheduled->value,
                    'next_retry_at' => null,
                    'last_error_code' => self::WORKER_LOST,
                    'last_error_message_safe' => $exhausted ? 'Доставка прервана.' : $message,
                    'updated_at' => now(),
                ]) === 1;

            if ($moved) {
                SubmissionDeliveryAttempt::query()
                    ->where('submission_delivery_id', $delivery->id)
                    ->whereNull('finished_at')
                    ->update([
                        'finished_at' => now(),
                        'status' => DeliveryOutcome::TransientFailure->value,
                        'error_code' => self::WORKER_LOST,
                        'safe_error_message' => 'Доставка прервана.',
                    ]);
                $recovered++;
            }
        }

        return $recovered;
    }
}
