<?php

namespace Tests\Support;

use App\Integrations\Http\HostResolver;

/**
 * Test-only DNS: fixed host → addresses map, nothing else resolves.
 */
final class FakeHostResolver implements HostResolver
{
    /** @var list<string> */
    public array $lookups = [];

    /**
     * @param  array<string, list<string>>  $hosts
     */
    public function __construct(private array $hosts) {}

    public function resolve(string $host): array
    {
        $this->lookups[] = $host;

        return $this->hosts[$host] ?? [];
    }
}
