<?php

namespace App\Integrations\Http;

/**
 * DNS lookup used by the outbound HTTP policy. Tests and E2E bind a fake; production uses the
 * system resolver. Returns every A and AAAA address of the host.
 */
interface HostResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
