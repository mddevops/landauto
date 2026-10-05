<?php

namespace App\Integrations\Http;

use RuntimeException;

/**
 * Policy refusal with a safe code and Russian message. `transient` is true only for DNS lookups
 * that returned nothing (may recover); unsafe URLs and blocked addresses are permanent.
 */
final class OutboundRequestRejected extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly bool $transient = false,
    ) {
        parent::__construct($message);
    }
}
