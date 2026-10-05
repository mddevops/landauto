<?php

namespace App\Console\Commands;

use App\Integrations\Delivery\DeliveryScheduler;
use Illuminate\Console\Command;

/**
 * Scheduled every minute: queues due delivery retries and reschedules deliveries stuck in
 * `processing` after a lost worker.
 */
class DispatchDueDeliveriesCommand extends Command
{
    protected $signature = 'integrations:dispatch-due-deliveries';

    protected $description = 'Queue due Submission delivery retries and recover stale deliveries';

    public function handle(DeliveryScheduler $scheduler): int
    {
        $result = $scheduler->dispatchDue();

        $this->info("Recovered: {$result['recovered']}, queued: {$result['dispatched']}.");

        return self::SUCCESS;
    }
}
