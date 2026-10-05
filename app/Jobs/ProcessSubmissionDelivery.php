<?php

namespace App\Jobs;

use App\Enums\DeliveryTrigger;
use App\Integrations\Delivery\DeliveryProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued attempt of one Delivery. The payload carries only the internal Delivery ID; retries are
 * scheduled through the Delivery row, not by the queue, so `$tries` stays at 1.
 */
class ProcessSubmissionDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public int $deliveryId,
        public DeliveryTrigger $trigger = DeliveryTrigger::Automatic,
    ) {}

    public function handle(DeliveryProcessor $processor): void
    {
        $processor->process($this->deliveryId, $this->trigger);
    }
}
