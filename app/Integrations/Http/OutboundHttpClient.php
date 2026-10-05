<?php

namespace App\Integrations\Http;

use App\Enums\IntegrationAuthType;
use App\Integrations\Delivery\DeliveryFailures;
use App\Integrations\Delivery\DeliveryResult;
use App\Models\IntegrationProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/**
 * The one transport for customer-configured HTTP destinations, shared by webhook / custom API
 * delivery and Test Connection: policy check, pinned address, no redirects, fixed timeouts,
 * bounded response read, auth built only from the profile's encrypted credentials, shared
 * classification. Nothing here is logged; only safe metadata is returned.
 */
final class OutboundHttpClient
{
    public const METHODS = ['POST', 'PUT', 'PATCH'];

    private const INVALID_CONFIG = 'Некорректная настройка интеграции.';

    public function __construct(private OutboundHttpPolicy $policy) {}

    /**
     * @param  array<array-key, mixed>  $headers  Stored route headers `{name, value}`, re-validated here.
     * @param  array<array-key, mixed>  $payload
     */
    public function send(IntegrationProfile $profile, string $method, string $url, array $headers, array $payload, string $idempotencyKey): DeliveryResult
    {
        if (! in_array($method, self::METHODS, true)) {
            return DeliveryResult::permanent('invalid_config', self::INVALID_CONFIG);
        }

        $final = [];

        foreach ($headers as $header) {
            if (! is_array($header) || ! is_string($header['name'] ?? null) || ! is_string($header['value'] ?? null)
                || ! SafeHeaders::isAllowedName($header['name']) || ! SafeHeaders::isAllowedValue($header['value'])) {
                return DeliveryResult::permanent('invalid_config', self::INVALID_CONFIG);
            }

            $final[strtolower($header['name'])] = $header['value'];
        }

        $auth = $this->authHeaders($profile);

        if ($auth === null) {
            return DeliveryResult::permanent('invalid_credentials', 'В профиле интеграции не заполнены данные доступа.');
        }

        try {
            $destination = $this->policy->check($url);
        } catch (OutboundRequestRejected $rejected) {
            return $rejected->transient
                ? DeliveryFailures::dns()
                : DeliveryResult::permanent($rejected->errorCode, $rejected->getMessage());
        }

        $final = [
            ...$final,
            'accept' => 'application/json',
            'user-agent' => (string) config('integrations.http.user_agent'),
            'idempotency-key' => $idempotencyKey,
            ...$auth,
        ];

        $cap = config()->integer('integrations.http.max_response_bytes');
        $headersStatus = null;
        $aborted = false;
        $startedAt = hrtime(true);

        try {
            // Must run on the cURL handler (no `stream` option): only it honours RESOLVE pinning.
            $response = Http::withOptions([
                'allow_redirects' => false,
                'connect_timeout' => config()->integer('integrations.http.connect_timeout'),
                'timeout' => config()->integer('integrations.http.timeout'),
                'verify' => true,
                'proxy' => '',
                'on_headers' => function (ResponseInterface $headers) use (&$headersStatus): void {
                    $headersStatus = $headers->getStatusCode();
                },
                'progress' => function (int $downloadTotal, int $downloaded) use ($cap, &$aborted): bool {
                    return $aborted = $downloaded > $cap;
                },
                'curl' => [CURLOPT_RESOLVE => [$destination->resolveEntry()]],
            ])->withHeaders($final)->send($method, $destination->url, ['json' => $payload]);
        } catch (RequestException $exception) {
            return DeliveryFailures::forHttpStatus($exception->response->status(), self::elapsed($startedAt), self::truncated($cap));
        } catch (ConnectionException $exception) {
            $latency = self::elapsed($startedAt);

            return match (true) {
                $aborted && $headersStatus !== null => DeliveryFailures::forHttpStatus($headersStatus, $latency, self::truncated($cap)),
                str_contains($exception->getMessage(), 'cURL error 28') => DeliveryFailures::timeout($latency),
                str_contains($exception->getMessage(), 'cURL error 6') => DeliveryFailures::dns(),
                default => DeliveryFailures::network($latency),
            };
        }

        return DeliveryFailures::forHttpStatus($response->status(), self::elapsed($startedAt), $this->metadata($response, $cap));
    }

    /**
     * @return array<string, scalar>
     */
    private static function truncated(int $cap): array
    {
        return ['response_bytes' => $cap, 'response_truncated' => true];
    }

    /**
     * @return array<string, string>|null Null when the configured auth lacks its secret.
     */
    private function authHeaders(IntegrationProfile $profile): ?array
    {
        $credentials = $profile->encrypted_credentials ?? [];
        $token = $credentials['token'] ?? '';
        $safe = fn (string $value): bool => $value !== '' && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;

        return match ($profile->auth_type) {
            IntegrationAuthType::None => [],
            IntegrationAuthType::Bearer => $safe($token) ? ['authorization' => 'Bearer '.$token] : null,
            IntegrationAuthType::Basic => $safe($credentials['username'] ?? '') && $safe($credentials['password'] ?? '')
                ? ['authorization' => 'Basic '.base64_encode($credentials['username'].':'.$credentials['password'])]
                : null,
            IntegrationAuthType::ApiKeyHeader => $this->apiKeyHeader($profile, $token, $safe($token)),
        };
    }

    /**
     * @return array<string, string>|null
     */
    private function apiKeyHeader(IntegrationProfile $profile, string $token, bool $tokenIsSafe): ?array
    {
        $name = $profile->settings_json['api_key_header'] ?? null;

        if (! $tokenIsSafe || ! is_string($name) || ! SafeHeaders::isAllowedName($name)) {
            return null;
        }

        return [strtolower($name) => $token];
    }

    /**
     * The body itself is discarded (cURL aborts past the cap); only its size, content type and a
     * well-formed request ID are kept.
     *
     * @return array<string, scalar>
     */
    private function metadata(Response $response, int $cap): array
    {
        $read = strlen($response->body());
        $metadata = ['response_bytes' => min($read, $cap)];

        if ($read > $cap) {
            $metadata['response_truncated'] = true;
        }

        $type = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));

        if (preg_match('/^[a-z0-9.+\/-]{1,80}$/', $type) === 1) {
            $metadata['content_type'] = $type;
        }

        $requestId = $response->header('X-Request-Id');

        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $requestId) === 1) {
            $metadata['request_id'] = $requestId;
        }

        return $metadata;
    }

    private static function elapsed(int|float $startedAt): int
    {
        return (int) intdiv((int) (hrtime(true) - $startedAt), 1_000_000);
    }
}
