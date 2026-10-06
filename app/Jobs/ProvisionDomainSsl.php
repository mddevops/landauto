<?php

namespace App\Jobs;

use App\Domains\DomainSsl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued SSL provisioning attempt of one custom domain. The payload carries only the internal ID;
 * retries are scheduled through the domain row, so `$tries` stays at 1.
 */
class ProvisionDomainSsl implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $domainId)
    {
        $this->timeout = max(10, (int) config('domains.ssl_timeout')) + 30;
    }

    public function handle(DomainSsl $ssl): void
    {
        $ssl->provision($this->domainId);
    }
}
