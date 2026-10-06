<?php

namespace App\Domains\Dns;

/**
 * DNS lookups for custom domain verification. Implementations return bounded, normalized answers
 * (lowercase hostnames without a trailing dot) and never throw on resolution failures.
 */
interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function txt(string $name): array;

    /**
     * @return list<string>
     */
    public function a(string $host): array;

    /**
     * @return list<string>
     */
    public function aaaa(string $host): array;

    /** The CNAME target of exactly this name, if any. */
    public function cname(string $host): ?string;
}
