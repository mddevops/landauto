<?php

namespace App\Domains;

use App\Models\SiteDomain;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Scheduled background checks: domains still waiting for DNS are re-checked every few minutes,
 * ready domains once a day to notice broken records. Workspaces without the `custom_domain`
 * entitlement are skipped.
 */
final class DomainReconciler
{
    private const BATCH = 100;

    public function __construct(private DomainVerifier $verifier, private CustomDomainAccess $access) {}

    /**
     * @return array{checked: int, skipped: int}
     */
    public function run(): array
    {
        $pendingBefore = now()->subMinutes(max(1, (int) config('domains.reconcile_after_minutes')));
        $readyBefore = now()->subDay();
        $checked = 0;
        $skipped = 0;

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
                    ->where(fn (Builder $due) => $due->whereNull('last_checked_at')->orWhere('last_checked_at', '<', $readyBefore))))
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
                $this->verifier->check($domain);
                $checked++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return ['checked' => $checked, 'skipped' => $skipped];
    }
}
