<?php

namespace App\Domains\Dns;

/**
 * Production resolver over the system DNS configuration. Only TXT/A/AAAA/CNAME records of the
 * customer's hostname are queried (never an HTTP request); answers are capped and lookup errors
 * count as "no records". Timeouts are those of the system resolver.
 */
final class SystemDnsResolver implements DnsResolver
{
    private const MAX_ANSWERS = 20;

    public function txt(string $name): array
    {
        return $this->values($name, DNS_TXT, function (array $record): ?string {
            $entries = $record['entries'] ?? null;

            return is_array($entries) ? implode('', array_map('strval', $entries)) : ($record['txt'] ?? null);
        });
    }

    public function a(string $host): array
    {
        return $this->values($host, DNS_A, fn (array $record): ?string => $record['ip'] ?? null);
    }

    public function aaaa(string $host): array
    {
        return $this->values($host, DNS_AAAA, fn (array $record): ?string => isset($record['ipv6']) ? strtolower($record['ipv6']) : null);
    }

    public function cname(string $host): ?string
    {
        foreach ($this->records($host, DNS_CNAME) as $record) {
            if (strtolower(rtrim((string) ($record['host'] ?? ''), '.')) === $host && isset($record['target'])) {
                return strtolower(rtrim((string) $record['target'], '.'));
            }
        }

        return null;
    }

    /**
     * @param  callable(array<string, mixed>): ?string  $extract
     * @return list<string>
     */
    private function values(string $name, int $type, callable $extract): array
    {
        $values = [];

        foreach ($this->records($name, $type) as $record) {
            $value = $extract($record);

            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(string $name, int $type): array
    {
        $records = @dns_get_record($name, $type);

        return is_array($records) ? array_slice($records, 0, self::MAX_ANSWERS) : [];
    }
}
