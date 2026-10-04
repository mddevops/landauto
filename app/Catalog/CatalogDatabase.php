<?php

namespace App\Catalog;

use RuntimeException;

/**
 * The Global Automotive Catalog is a separate physical database (D-102). Catalog schema
 * commands must never run against the main application database.
 */
final class CatalogDatabase
{
    public const CONNECTION = 'catalog';

    public const MIGRATIONS = 'database/migrations/catalog';

    public function assertSeparateFromMain(): void
    {
        $default = (string) config('database.default');

        if ($default === self::CONNECTION) {
            throw new RuntimeException('The main application connection must not be the catalog connection.');
        }

        /** @var array<string, mixed> $main */
        $main = (array) config("database.connections.{$default}", []);
        /** @var array<string, mixed> $catalog */
        $catalog = (array) config('database.connections.'.self::CONNECTION, []);

        $catalogTarget = $this->target($catalog);

        if ($catalogTarget !== null && $catalogTarget === $this->target($main)) {
            throw new RuntimeException('The catalog connection points at the main application database. Configure CATALOG_DB_* for a separate database.');
        }
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function target(array $connection): ?string
    {
        $url = trim((string) ($connection['url'] ?? ''));

        if ($url !== '') {
            return 'url:'.strtolower($url);
        }

        $driver = (string) ($connection['driver'] ?? '');
        $database = (string) ($connection['database'] ?? '');

        if ($driver === 'sqlite') {
            // Every in-memory SQLite connection is its own database.
            return $database === ':memory:' ? null : 'sqlite:'.strtolower(str_replace('\\', '/', (string) (realpath($database) ?: $database)));
        }

        return strtolower(implode(':', [
            $driver === 'mariadb' ? 'mysql' : $driver,
            (string) ($connection['host'] ?? ''),
            (string) ($connection['port'] ?? ''),
            $database,
        ]));
    }
}
