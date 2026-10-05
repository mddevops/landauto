<?php

namespace App\Integrations\Delivery;

use App\Enums\DeliveryOutcome;

/**
 * Normalized adapter result. `safeMessage` is a short Russian text for customers; `metadata`
 * holds only allowlisted scalar facts (content type, size, request ID), never bodies or secrets.
 */
final readonly class DeliveryResult
{
    private const MAX_METADATA = 10;

    /** @var array<string, scalar|null> */
    public array $metadata;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public DeliveryOutcome $outcome,
        public ?int $httpStatus = null,
        public ?string $errorCode = null,
        public ?string $safeMessage = null,
        public ?string $providerCode = null,
        array $metadata = [],
        public ?int $latencyMs = null,
    ) {
        $safe = [];

        foreach ($metadata as $key => $value) {
            if (count($safe) < self::MAX_METADATA && preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) === 1 && (is_scalar($value) || $value === null)) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 120) : $value;
            }
        }

        $this->metadata = $safe;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(?int $httpStatus = null, array $metadata = [], ?int $latencyMs = null, ?string $providerCode = null): self
    {
        return new self(DeliveryOutcome::Success, $httpStatus, null, null, $providerCode, $metadata, $latencyMs);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function transient(string $errorCode, string $safeMessage, ?int $httpStatus = null, array $metadata = [], ?int $latencyMs = null): self
    {
        return new self(DeliveryOutcome::TransientFailure, $httpStatus, $errorCode, $safeMessage, null, $metadata, $latencyMs);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function permanent(string $errorCode, string $safeMessage, ?int $httpStatus = null, array $metadata = [], ?int $latencyMs = null): self
    {
        return new self(DeliveryOutcome::PermanentFailure, $httpStatus, $errorCode, $safeMessage, null, $metadata, $latencyMs);
    }

    public function succeeded(): bool
    {
        return $this->outcome === DeliveryOutcome::Success;
    }
}
