<?php

namespace App\Integrations\Delivery;

use App\Enums\DeliveryOutcome;
use App\Enums\DeliveryStatus;
use App\Enums\DeliveryTrigger;
use App\Integrations\Mapping\MappingFailure;
use App\Models\SubmissionDelivery;
use App\Models\SubmissionDeliveryAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs one attempt of a Delivery: atomically claims it, calls the adapter, records the Attempt
 * and moves the Delivery to delivered / retry_scheduled / failed (FORMS_AND_INTEGRATIONS.md §25–29).
 * A Delivery that is not claimable (already processing, delivered, failed or not yet due) is
 * left untouched, so duplicate jobs never produce a second provider call.
 */
final class DeliveryProcessor
{
    public const TEMPORARY_MESSAGE = 'Сервис временно недоступен. Доставка будет повторена.';

    private const ROUTE_DISABLED = 'route_disabled';

    public function __construct(private DeliveryAdapters $adapters) {}

    public function process(int $deliveryId, DeliveryTrigger $trigger = DeliveryTrigger::Automatic): void
    {
        if (! $this->claim($deliveryId)) {
            return;
        }

        $delivery = SubmissionDelivery::query()
            ->with(['submission.form.site', 'route.form', 'route.binding.profile'])
            ->findOrFail($deliveryId);

        $attempt = new SubmissionDeliveryAttempt;
        $attempt->forceFill([
            'submission_delivery_id' => $delivery->id,
            'attempt_number' => $delivery->attempt_count,
            'trigger' => $trigger,
            'started_at' => now(),
        ])->save();

        $startedAt = hrtime(true);
        $result = $this->attempt($delivery);
        $latency = $result->latencyMs ?? intdiv(hrtime(true) - $startedAt, 1_000_000);

        $attempt->forceFill([
            'finished_at' => now(),
            'status' => $result->outcome,
            'http_status' => $result->httpStatus,
            'provider_code' => $result->providerCode,
            'latency_ms' => $latency,
            'response_summary_json' => $result->metadata === [] ? null : $result->metadata,
            'error_code' => $result->errorCode,
            'safe_error_message' => $result->safeMessage,
        ])->save();

        $this->finish($delivery, $result);
    }

    /**
     * Single conditional UPDATE: only one worker can move a due delivery into `processing`.
     */
    private function claim(int $deliveryId): bool
    {
        $now = now();

        return SubmissionDelivery::query()
            ->whereKey($deliveryId)
            ->whereIn('status', DeliveryStatus::claimable())
            ->where(fn (Builder $query) => $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now))
            ->update([
                'status' => DeliveryStatus::Processing->value,
                'attempt_count' => DB::raw('attempt_count + 1'),
                'last_attempt_at' => $now,
                'next_retry_at' => null,
                'updated_at' => $now,
            ]) === 1;
    }

    private function attempt(SubmissionDelivery $delivery): DeliveryResult
    {
        $route = $delivery->route;

        if (! $route->isActive()) {
            return DeliveryResult::permanent(self::ROUTE_DISABLED, 'Маршрут доставки выключен.');
        }

        if ($route->binding !== null && ! $route->binding->profile->isActive()) {
            return DeliveryResult::permanent('profile_disabled', 'Профиль интеграции выключен.');
        }

        if ($route->binding !== null && ! $route->binding->isActive()) {
            return DeliveryResult::permanent('binding_disabled', 'Интеграция выключена на сайте.');
        }

        $adapter = $this->adapters->for($delivery->destination_type);

        if ($adapter === null) {
            return DeliveryResult::permanent('adapter_missing', 'Этот способ доставки недоступен.');
        }

        try {
            return $adapter->deliver($delivery);
        } catch (MappingFailure $failure) {
            return DeliveryResult::permanent($failure->errorCode, $failure->getMessage());
        } catch (Throwable $exception) {
            Log::error('delivery.adapter_exception', [
                'delivery' => $delivery->public_id,
                'provider_type' => $delivery->destination_type->value,
                'attempt' => $delivery->attempt_count,
                'exception' => $exception::class,
            ]);

            return DeliveryResult::transient('internal_error', self::TEMPORARY_MESSAGE);
        }
    }

    private function finish(SubmissionDelivery $delivery, DeliveryResult $result): void
    {
        $status = match (true) {
            $result->errorCode === self::ROUTE_DISABLED => DeliveryStatus::Cancelled,
            $result->outcome === DeliveryOutcome::Success => DeliveryStatus::Delivered,
            $result->outcome === DeliveryOutcome::TransientFailure && $this->retryDelay($delivery) !== null => DeliveryStatus::RetryScheduled,
            default => DeliveryStatus::Failed,
        };

        $delay = $status === DeliveryStatus::RetryScheduled ? $this->retryDelay($delivery) : null;

        $delivery->forceFill([
            'status' => $status,
            'next_retry_at' => $delay === null ? null : now()->addSeconds($delay),
            'delivered_at' => $status === DeliveryStatus::Delivered ? now() : $delivery->delivered_at,
            'last_http_status' => $result->httpStatus,
            'last_error_code' => $result->succeeded() ? null : $result->errorCode,
            'last_error_message_safe' => $result->succeeded() ? null : $result->safeMessage,
        ])->save();

        if (! $result->succeeded()) {
            Log::warning('delivery.attempt_failed', [
                'delivery' => $delivery->public_id,
                'profile' => $delivery->route->binding?->profile->public_id,
                'provider_type' => $delivery->destination_type->value,
                'http_status' => $result->httpStatus,
                'error_code' => $result->errorCode,
                'attempt' => $delivery->attempt_count,
                'status' => $status->value,
            ]);
        }
    }

    /**
     * Seconds until the next automatic attempt, or null when the retry budget is exhausted.
     */
    private function retryDelay(SubmissionDelivery $delivery): ?int
    {
        $delay = config()->array('integrations.retry_delays')[$delivery->attempt_count - 1] ?? null;

        return is_int($delay) ? $delay : null;
    }
}
