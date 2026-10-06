<?php

namespace App\Domains\Ssl;

use Illuminate\Support\Facades\Cache;

/**
 * Testing / E2E driver (`CUSTOM_DOMAIN_SSL_DRIVER=fake`, only in the testing and e2e
 * environments). Issues a 90-day "certificate" unless a failure was scheduled for the hostname.
 */
final class FakeSslProvisioner implements SslProvisioner
{
    private const KEY = 'domains.fake-ssl';

    public function enabled(): bool
    {
        return true;
    }

    /**
     * @param  SslResult::TEMPORARY|SslResult::PERMANENT|null  $outcome  null restores success
     */
    public function failNext(string $hostname, ?string $outcome): void
    {
        $outcomes = $this->outcomes();
        $outcomes[strtolower($hostname)] = $outcome;
        Cache::forever(self::KEY, array_filter($outcomes));
    }

    public function provision(string $hostname): SslResult
    {
        return match ($this->outcomes()[strtolower($hostname)] ?? null) {
            SslResult::TEMPORARY => SslResult::temporary(),
            SslResult::PERMANENT => SslResult::permanent(),
            default => SslResult::issued(now()->addDays(90)),
        };
    }

    /**
     * @return array<string, string|null>
     */
    private function outcomes(): array
    {
        $outcomes = Cache::get(self::KEY);

        return is_array($outcomes) ? $outcomes : [];
    }
}
