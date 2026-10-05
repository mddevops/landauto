<?php

namespace App\Integrations\Mapping;

use RuntimeException;

/**
 * A mapping that cannot produce a valid payload; always a permanent delivery failure. The message
 * is Russian, short and contains no lead data.
 */
final class MappingFailure extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $safeMessage)
    {
        parent::__construct($safeMessage);
    }
}
