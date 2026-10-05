<?php

namespace App\Integrations\Testing;

use App\Integrations\Http\HostResolver;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Deterministic outbound world for browser E2E; never bound outside the `testing`/`e2e`
 * environments. Only the DNS answers and the HTTP transport are fake: the real
 * `OutboundHttpPolicy`, auth builder and classifier still run, so private destinations are
 * refused exactly as in production and no request ever leaves the machine.
 */
final class E2eIntegrationFakes implements HostResolver
{
    public const CRM_HOST = 'hooks.e2e.test';

    /** Resolves to a private address, so the SSRF policy must refuse it. */
    public const PRIVATE_HOST = 'private.e2e.test';

    private const ADDRESSES = [
        self::CRM_HOST => ['93.184.216.34'],
        self::PRIVATE_HOST => ['10.20.30.40'],
    ];

    public function resolve(string $host): array
    {
        return self::ADDRESSES[strtolower($host)] ?? [];
    }

    /**
     * Fake CRM on `https://hooks.e2e.test`:
     *  - `/`             Test Connection target: 200 with a Bearer token, otherwise 401;
     *  - `/leads`        201 when the mapped payload has a non-empty `phone`, a positive integer
     *                    `price` and a non-empty `dealer`, otherwise 422;
     *  - `/flaky`        503 on the first request of each Idempotency-Key, then 200;
     *  - `/unauthorized` always 401.
     */
    public static function install(): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $request): PromiseInterface => self::respond($request));
    }

    private static function respond(Request $request): PromiseInterface
    {
        $authorized = str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ');

        if (parse_url($request->url(), PHP_URL_HOST) !== self::CRM_HOST) {
            return Factory::response('', 404);
        }

        return match (parse_url($request->url(), PHP_URL_PATH) ?? '/') {
            '', '/' => Factory::response(['ok' => $authorized], $authorized ? 200 : 401),
            '/leads' => self::leads($request),
            '/flaky' => Cache::add('e2e-flaky:'.($request->header('Idempotency-Key')[0] ?? ''), true, 3600)
                ? Factory::response(['error' => 'unavailable'], 503)
                : Factory::response(['ok' => true], 200),
            '/unauthorized' => Factory::response(['error' => 'unauthorized'], 401),
            default => Factory::response('', 404),
        };
    }

    private static function leads(Request $request): PromiseInterface
    {
        $data = $request->data();
        $valid = is_string($data['phone'] ?? null) && $data['phone'] !== ''
            && is_int($data['price'] ?? null) && $data['price'] > 0
            && is_string($data['dealer'] ?? null) && $data['dealer'] !== '';

        return $valid
            ? Factory::response(['id' => 'e2e-lead'], 201, ['X-Request-Id' => 'e2e-lead'])
            : Factory::response(['error' => 'invalid'], 422);
    }
}
