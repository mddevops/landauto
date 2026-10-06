<?php

namespace App\Domains\Ssl;

use App\Domains\CustomHostname;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Throwable;

/**
 * Runs the infrastructure provisioning script configured in `CUSTOM_DOMAIN_SSL_COMMAND` with the
 * hostname as its only argument (argument vector, never a shell string). Script contract:
 * exit 0 = certificate installed (optional stdout line `expires_at=<ISO-8601>`), exit 75
 * (EX_TEMPFAIL) = temporary failure, anything else = permanent failure. Output is never logged
 * and only its first bytes are parsed.
 */
final class CommandSslProvisioner implements SslProvisioner
{
    public const EXIT_TEMPORARY = 75;

    private const MAX_OUTPUT = 4096;

    public function enabled(): bool
    {
        $command = config('domains.ssl_command');

        return is_string($command) && $command !== '' && is_file($command);
    }

    public function provision(string $hostname): SslResult
    {
        if (CustomHostname::problem($hostname) !== null || CustomHostname::normalize($hostname) !== $hostname) {
            throw new InvalidArgumentException('Refusing to provision an invalid hostname.');
        }

        if (! $this->enabled()) {
            return SslResult::temporary();
        }

        try {
            $result = Process::timeout(max(10, (int) config('domains.ssl_timeout')))
                ->run([(string) config('domains.ssl_command'), $hostname]);
        } catch (Throwable) {
            return SslResult::temporary();
        }

        return match ($result->exitCode()) {
            0 => SslResult::issued($this->expiresAt(substr($result->output(), 0, self::MAX_OUTPUT))),
            self::EXIT_TEMPORARY => SslResult::temporary(),
            default => SslResult::permanent(),
        };
    }

    private function expiresAt(string $output): ?CarbonImmutable
    {
        if (preg_match('/^expires_at=(\S{10,40})$/m', $output, $match) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($match[1]);
        } catch (Throwable) {
            return null;
        }
    }
}
