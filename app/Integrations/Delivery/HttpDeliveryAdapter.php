<?php

namespace App\Integrations\Delivery;

use App\Enums\DeliveryDestinationType;
use App\Integrations\Http\OutboundHttpClient;
use App\Integrations\Mapping\FieldMapper;
use App\Integrations\Mapping\MappingContext;
use App\Models\IntegrationProfile;
use App\Models\SubmissionDelivery;
use Illuminate\Support\Str;

/**
 * Webhook / custom API delivery (FORMS_AND_INTEGRATIONS.md §20): the profile base URL plus the
 * route path, the route method and safe headers, the mapped JSON payload and the Delivery
 * public ID as `Idempotency-Key`. Transport, policy and classification are shared with Test
 * Connection through `OutboundHttpClient`.
 */
final class HttpDeliveryAdapter implements DeliveryAdapter
{
    public function __construct(private OutboundHttpClient $client, private FieldMapper $mapper) {}

    public function supports(DeliveryDestinationType $type): bool
    {
        return $type === DeliveryDestinationType::Webhook || $type === DeliveryDestinationType::CustomApi;
    }

    public function deliver(SubmissionDelivery $delivery): DeliveryResult
    {
        $route = $delivery->route;
        $binding = $route->binding;

        if ($binding === null) {
            return DeliveryResult::permanent('invalid_config', 'Некорректная настройка интеграции.');
        }

        $profile = $binding->profile;
        $settings = $route->settings_json ?? [];
        $path = is_string($settings['path'] ?? null) ? $settings['path'] : '';
        $method = is_string($settings['method'] ?? null) ? $settings['method'] : 'POST';
        $headers = is_array($settings['headers'] ?? null) ? $settings['headers'] : [];

        $payload = $this->mapper->map(FieldMapper::fromStored($route->mapping_json), MappingContext::for($delivery->submission, $binding));

        return $this->client->send($profile, $method, self::url($profile, $path), $headers, $payload, $delivery->public_id);
    }

    public function testConnection(IntegrationProfile $profile): DeliveryResult
    {
        return $this->client->send(
            $profile,
            'POST',
            self::url($profile, ''),
            [],
            ['event' => 'landflow.test_connection'],
            'test-'.Str::lower((string) Str::ulid()),
        );
    }

    public static function url(IntegrationProfile $profile, string $path): string
    {
        $base = (string) $profile->base_url;

        return $path === '' ? $base : rtrim($base, '/').'/'.ltrim($path, '/');
    }
}
