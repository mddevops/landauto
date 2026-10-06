<?php

namespace App\Domains;

use App\Enums\DomainSslStatus;
use App\Models\SiteDomain;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Scheduled background checks: domains still waiting for DNS are re-checked every few minutes,
 * ready domains once a day to notice broken records; ready domains without a certificate get
 * provisioning requested when their backoff has elapsed. Workspaces without the `custom_domain`
 * entitlement are skipped.
 */
final class DomainReconciler
{
    private const BATCH = 100;

    public function __construct(
        private DomainVerifier $verifier,
        private DomainSsl $ssl,
        private CustomDomainAccess $access,
    ) {}

    /**
     * @return array{checked: int, skipped: int, ssl_requested: int}
     */
    public function run(): array
    {
        $this->ssl->recoverStale();

        $pendingBefore = now()->subMinutes(max(1, (int) config('domains.reconcile_after_minutes')));
        $readyBefore = now()->subDay();
        $checked = 0;
        $skipped = 0;
        $sslRequested = 0;

        $domains = SiteDomain::query()
            ->with('site.workspace')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $pending) => $pending
                    ->where(fn (Builder $notReady) => $notReady
                        ->where('verification_status', '!=', 'verified')
                        ->orWhere('routing_status', '!=', 'verified'))
                    ->where(fn (Builder $due) => $due->whereNull('last_checked_at')->orWhere('last_checked_at', '<', $pendingBefore)))
                ->orWhere(fn (Builder $ready) => $ready
                    ->where('verification_status', 'verified')
                    ->where('routing_status', 'verified')
                    ->where(fn (Builder $due) => $due
                        ->whereNull('last_checked_at')
                        ->orWhere('last_checked_at', '<', $readyBefore)
                        ->orWhere(fn (Builder $ssl) => $ssl
                            ->where('ssl_status', DomainSslStatus::Pending)
                            ->where(fn (Builder $retry) => $retry->whereNull('ssl_retry_at')->orWhere('ssl_retry_at', '<=', now()))))))
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->limit(self::BATCH)
            ->get();

        foreach ($domains as $domain) {
            if (! $this->access->entitled($domain->site)) {
                $skipped++;

                continue;
            }

            try {
                if (! $domain->isDnsReady() || $domain->last_checked_at === null || $domain->last_checked_at->lt($readyBefore)) {
                    $this->verifier->check($domain);
                    $checked++;
                }

                if ($this->ssl->request($domain)) {
                    $sslRequested++;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return ['checked' => $checked, 'skipped' => $skipped, 'ssl_requested' => $sslRequested];
    }
}
