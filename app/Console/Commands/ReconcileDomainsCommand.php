<?php

namespace App\Console\Commands;

use App\Domains\DomainReconciler;
use Illuminate\Console\Command;

/**
 * Scheduled: re-checks DNS of custom domains that are still pending and, daily, of ready ones.
 */
class ReconcileDomainsCommand extends Command
{
    protected $signature = 'domains:reconcile';

    protected $description = 'Re-check DNS and SSL state of custom domains';

    public function handle(DomainReconciler $reconciler): int
    {
        $result = $reconciler->run();

        $this->info("Checked: {$result['checked']}, skipped: {$result['skipped']}, SSL requested: {$result['ssl_requested']}.");

        return self::SUCCESS;
    }
}
