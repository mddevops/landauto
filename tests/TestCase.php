<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return Application
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        // Tests refresh the database, so they must only ever run against in-memory SQLite,
        // never the development database (e.g. when configuration is cached or DB_* is set in the shell).
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException("Tests must use in-memory SQLite, got [{$connection}:{$database}]. Run \"php artisan config:clear\" and use \"composer test\".");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Backend tests must not depend on the production frontend build (public/build).
        // Tests that exercise real Vite output can opt back in with $this->withVite().
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
