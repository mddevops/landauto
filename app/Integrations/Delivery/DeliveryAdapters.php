<?php

namespace App\Integrations\Delivery;

use App\Enums\DeliveryDestinationType;

/**
 * Registered provider adapters (tag `integrations.delivery_adapters`). The first adapter that
 * supports a destination type handles it.
 */
final class DeliveryAdapters
{
    public const TAG = 'integrations.delivery_adapters';

    /**
     * @param  iterable<DeliveryAdapter>  $adapters
     */
    public function __construct(private iterable $adapters) {}

    public function for(DeliveryDestinationType $type): ?DeliveryAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($type)) {
                return $adapter;
            }
        }

        return null;
    }
}
