<?php

namespace Tests\Concerns;

/**
 * Migrates the isolated in-memory catalog connection for each test (D-102).
 */
trait RefreshCatalogDatabase
{
    protected function setUpRefreshCatalogDatabase(): void
    {
        $this->artisan('catalog:migrate', ['--force' => true])->assertSuccessful();
    }
}
