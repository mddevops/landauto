<?php

namespace Tests;

use App\Domains\Dns\DnsResolver;
use App\Domains\Dns\FakeDnsResolver;
use App\Integrations\Http\HostResolver;
use App\Models\Site;
use App\Publishing\Rendering\PageRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use RuntimeException;
use Tests\Support\FakeHostResolver;
use Tests\Support\FakePageRenderer;

abstract class TestCase extends BaseTestCase
{
    protected const DEALER_NUMBER = '100000000001';

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

        $catalog = $app['config']->get('database.connections.catalog');

        if (($catalog['driver'] ?? null) !== 'sqlite' || ($catalog['database'] ?? null) !== ':memory:') {
            throw new RuntimeException('Tests must use an in-memory SQLite catalog connection. Run "php artisan config:clear" and use "composer test".');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Backend tests must not depend on the production frontend build (public/build).
        // Tests that exercise real Vite output can opt back in with $this->withVite().
        $this->withoutVite();

        // The compiled publish renderer is a build artifact; see NodePageRendererTest.
        $this->app->instance(PageRenderer::class, new FakePageRenderer);

        // No real network in tests: unfaked HTTP fails, and DNS resolves nothing unless a test binds hosts.
        Http::preventStrayRequests();
        $this->app->instance(HostResolver::class, new FakeHostResolver([]));
        $this->app->instance(DnsResolver::class, new FakeDnsResolver);
    }

    /**
     * Gives a Site a known public number so a test can address it at `localhost/{number}`. The
     * model keeps the number immutable, so the table is updated directly.
     */
    protected function withPublicNumber(Site $site, string $number = self::DEALER_NUMBER): Site
    {
        Site::query()->whereKey($site->id)->update(['public_number' => $number]);
        $site->forceFill(['public_number' => $number])->syncOriginalAttribute('public_number');

        return $site;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
