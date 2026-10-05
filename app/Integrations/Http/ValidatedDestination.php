<?php

namespace App\Integrations\Http;

/**
 * A URL that passed the outbound policy, with the vetted address the connection is pinned to.
 */
final readonly class ValidatedDestination
{
    public function __construct(
        public string $url,
        public string $host,
        public int $port,
        public string $ip,
    ) {}

    /**
     * cURL `RESOLVE` entry: the request connects only to the vetted address, so a second DNS
     * answer (rebinding) can never redirect it to an internal host.
     */
    public function resolveEntry(): string
    {
        $address = str_contains($this->ip, ':') ? "[{$this->ip}]" : $this->ip;

        return "{$this->host}:{$this->port}:{$address}";
    }
}
