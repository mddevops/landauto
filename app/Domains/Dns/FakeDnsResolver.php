<?php

namespace App\Domains\Dns;

use Illuminate\Support\Facades\Cache;

/**
 * Testing / E2E resolver (`CUSTOM_DOMAIN_DNS_DRIVER=fake`, only in the testing and e2e
 * environments). Records live in the application cache so a Playwright spec can set them through
 * `domains:fake-dns` in another process; nothing is ever looked up on the network.
 */
final class FakeDnsResolver implements DnsResolver
{
    private const KEY = 'domains.fake-dns';

    /**
     * @param  list<string>  $values
     */
    public function set(string $type, string $name, array $values): void
    {
        $records = $this->all();
        $records[strtolower($type)][strtolower($name)] = $values;
        Cache::forever(self::KEY, $records);
    }

    public function forget(string $name): void
    {
        $records = $this->all();

        foreach (array_keys($records) as $type) {
            unset($records[$type][strtolower($name)]);
        }

        Cache::forever(self::KEY, $records);
    }

    public function txt(string $name): array
    {
        return $this->get('txt', $name);
    }

    public function a(string $host): array
    {
        return $this->get('a', $host);
    }

    public function aaaa(string $host): array
    {
        return $this->get('aaaa', $host);
    }

    public function cname(string $host): ?string
    {
        return $this->get('cname', $host)[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function get(string $type, string $name): array
    {
        return $this->all()[$type][strtolower($name)] ?? [];
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private function all(): array
    {
        $records = Cache::get(self::KEY);

        return is_array($records) ? $records : [];
    }
}
