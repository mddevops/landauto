<?php

namespace App\Integrations\Delivery;

use App\Enums\DeliveryDestinationType;
use App\Models\IntegrationProfile;
use App\Models\SubmissionDelivery;

/**
 * Provider adapter contract (FORMS_AND_INTEGRATIONS.md §21). Adapters never throw for provider
 * failures: they classify them into a normalized result. They must not log or return secrets,
 * Authorization headers, raw Submission payloads or full provider responses.
 */
interface DeliveryAdapter
{
    public function supports(DeliveryDestinationType $type): bool;

    public function deliver(SubmissionDelivery $delivery): DeliveryResult;

    /**
     * Server-side connectivity check of a profile with the same transport as real delivery,
     * without creating a Submission.
     */
    public function testConnection(IntegrationProfile $profile): DeliveryResult;
}
