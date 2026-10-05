<?php

namespace Tests\Support;

use App\Enums\DeliveryDestinationType;
use App\Integrations\Delivery\DeliveryAdapter;
use App\Integrations\Delivery\DeliveryResult;
use App\Models\IntegrationProfile;
use App\Models\SubmissionDelivery;
use Closure;

/**
 * Test-only adapter: returns queued results (or the default success) and records calls.
 */
final class FakeDeliveryAdapter implements DeliveryAdapter
{
    /** @var list<string> Delivery public IDs in call order. */
    public array $calls = [];

    /** @var list<DeliveryResult|Closure(SubmissionDelivery): DeliveryResult> */
    private array $results = [];

    public function queue(DeliveryResult|Closure ...$results): self
    {
        array_push($this->results, ...$results);

        return $this;
    }

    public function supports(DeliveryDestinationType $type): bool
    {
        return true;
    }

    public function deliver(SubmissionDelivery $delivery): DeliveryResult
    {
        $this->calls[] = $delivery->public_id;
        $result = array_shift($this->results) ?? DeliveryResult::success(200);

        return $result instanceof Closure ? $result($delivery) : $result;
    }

    public function testConnection(IntegrationProfile $profile): DeliveryResult
    {
        return DeliveryResult::success(200);
    }
}
