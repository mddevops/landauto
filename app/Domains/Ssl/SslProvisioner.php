<?php

namespace App\Domains\Ssl;

/**
 * Asks Landflow infrastructure to obtain (ACME HTTP-01) and install a certificate for one already
 * routed hostname. Implementations never return or persist certificate or key material.
 */
interface SslProvisioner
{
    /**
     * Whether this driver can issue certificates at all.
     */
    public function enabled(): bool;

    public function provision(string $hostname): SslResult;
}
