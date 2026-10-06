<?php

namespace App\Domains\Ssl;

/**
 * Default driver: certificates are not issued automatically (no infrastructure configured).
 */
final class NoneSslProvisioner implements SslProvisioner
{
    public function enabled(): bool
    {
        return false;
    }

    public function provision(string $hostname): SslResult
    {
        return SslResult::temporary();
    }
}
