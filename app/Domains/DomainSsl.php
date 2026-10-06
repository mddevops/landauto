<?php

namespace App\Domains;

use App\Domains\Ssl\SslProvisioner;
use App\Domains\Ssl\SslResult;
use App\Enums\DomainSslStatus;
use App\Enums\SiteStatus;
use App\Jobs\ProvisionDomainSsl;
use App\Models\SiteDomain;
use Illuminate\Support\Facades\Log;

/**
 * SSL lifecycle of a custom hostname (P7-003, D-111): requested only once ownership and routing
 * are verified (ACME HTTP-01 needs the host to reach Landflow), issued by infrastructure through
 * {@see SslProvisioner}, recorded here as metadata only. Temporary failures retry with backoff a
 * bounded number of times; permanent failures wait for a manual retry, keeping well inside the
 * CA's per-hostname failed-validation limits. Renewal stays with infrastructure.
 */
final class DomainSsl
{
    public const MAX_AUTOMATIC_ATTEMPTS = 3;

    private const RETRY_BASE_MINUTES = 15;

    /** Safe Russian messages per error code. */
    private const MESSAGES = [
        'ssl_not_configured' => 'Автоматический выпуск сертификатов ещё не настроен в Landflow.',
        'ssl_temporary' => 'Не удалось выпустить сертификат. Повторим попытку автоматически.',
        'ssl_failed' => 'Не удалось выпустить сертификат. Проверьте DNS-записи и повторите выпуск.',
    ];

    public function __construct(private SslProvisioner $provisioner, private CustomDomainAccess $access) {}

    /**
     * Russian reason why a certificate cannot be requested now, or null when eligible.
     */
    public function ineligibility(SiteDomain $domain): ?string
    {
        $site = $domain->site;

        return match (true) {
            $site->status !== SiteStatus::Active => 'Сайт находится в архиве.',
            ! $domain->isDnsReady() => 'Сначала подтвердите владение доменом и направьте его на Landflow.',
            ! $this->access->entitled($site) => 'Тариф пространства не включает собственные домены.',
            SiteDomain::query()
                ->where('hostname', $domain->hostname)
                ->whereKeyNot($domain->id)
                ->where('ssl_status', DomainSslStatus::Active)
                ->exists() => 'Этот домен уже подключён к другому сайту Landflow.',
            default => null,
        };
    }

    /**
     * Queues provisioning when eligible. Automatic requests (after a DNS check, scheduler) respect
     * the backoff and never restart a failed domain; a manual retry resets the attempt counter.
     */
    public function request(SiteDomain $domain, bool $manual = false): bool
    {
        if (in_array($domain->ssl_status, [DomainSslStatus::Active, DomainSslStatus::Provisioning], true)
            || $this->ineligibility($domain) !== null) {
            return false;
        }

        if (! $manual && ($domain->ssl_status === DomainSslStatus::Failed || ($domain->ssl_retry_at?->isFuture() ?? false))) {
            return false;
        }

        if (! $this->provisioner->enabled()) {
            $this->error($domain, 'ssl_not_configured');
            $domain->ssl_retry_at = now()->addHour();
            $domain->save();

            return false;
        }

        $claimed = SiteDomain::query()
            ->whereKey($domain->id)
            ->whereIn('ssl_status', [DomainSslStatus::Pending, DomainSslStatus::Failed])
            ->update([
                'ssl_status' => DomainSslStatus::Provisioning,
                'ssl_attempts' => $manual ? 0 : $domain->ssl_attempts,
                'ssl_retry_at' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $domain->refresh();
        Log::info('custom_domain.ssl_requested', $this->context($domain) + ['manual' => $manual]);
        ProvisionDomainSsl::dispatch($domain->id)->afterCommit();

        return true;
    }

    /**
     * Queued attempt; eligibility is re-checked because DNS or the entitlement may have changed.
     */
    public function provision(int $domainId): void
    {
        $domain = SiteDomain::query()->with('site.workspace')->find($domainId);

        if ($domain === null || $domain->ssl_status !== DomainSslStatus::Provisioning) {
            return;
        }

        if ($this->ineligibility($domain) !== null) {
            $domain->ssl_status = DomainSslStatus::Pending;
            $domain->save();

            return;
        }

        $result = $this->provisioner->provision($domain->hostname);

        $domain = SiteDomain::query()->with('site')->find($domainId);

        if ($domain === null || $domain->ssl_status !== DomainSslStatus::Provisioning) {
            return;
        }

        $this->record($domain, $result);
    }

    /**
     * Provisioning rows left behind by a lost worker go back to pending for the next attempt.
     */
    public function recoverStale(): int
    {
        return SiteDomain::query()
            ->where('ssl_status', DomainSslStatus::Provisioning)
            ->where('updated_at', '<', now()->subSeconds(max(10, (int) config('domains.ssl_timeout')) + 900))
            ->update(['ssl_status' => DomainSslStatus::Pending, 'ssl_retry_at' => now(), 'updated_at' => now()]);
    }

    private function record(SiteDomain $domain, SslResult $result): void
    {
        if ($result->outcome === SslResult::ISSUED) {
            $domain->ssl_status = DomainSslStatus::Active;
            $domain->ssl_issued_at = now();
            $domain->ssl_expires_at = $result->expiresAt;
            $domain->ssl_attempts = 0;
            $domain->ssl_retry_at = null;

            if (str_starts_with((string) $domain->last_error_code, 'ssl_')) {
                $domain->last_error_code = null;
                $domain->last_error_message_safe = null;
            }

            $domain->save();
            Log::info('custom_domain.ssl_active', $this->context($domain));

            return;
        }

        $domain->ssl_attempts = min(255, $domain->ssl_attempts + 1);

        if ($result->outcome === SslResult::TEMPORARY && $domain->ssl_attempts < self::MAX_AUTOMATIC_ATTEMPTS) {
            $domain->ssl_status = DomainSslStatus::Pending;
            $domain->ssl_retry_at = now()->addMinutes(self::RETRY_BASE_MINUTES * 2 ** ($domain->ssl_attempts - 1));
            $this->error($domain, 'ssl_temporary');
        } else {
            $domain->ssl_status = DomainSslStatus::Failed;
            $domain->ssl_retry_at = null;
            $this->error($domain, 'ssl_failed');
        }

        $domain->save();
        Log::warning('custom_domain.ssl_failed', $this->context($domain) + ['outcome' => $result->outcome, 'attempts' => $domain->ssl_attempts]);
    }

    private function error(SiteDomain $domain, string $code): void
    {
        $domain->last_error_code = $code;
        $domain->last_error_message_safe = self::MESSAGES[$code];
    }

    /**
     * @return array<string, string>
     */
    private function context(SiteDomain $domain): array
    {
        return ['site' => $domain->site->public_id, 'domain' => $domain->public_id, 'hostname' => $domain->hostname];
    }
}
