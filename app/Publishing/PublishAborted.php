<?php

namespace App\Publishing;

use App\Enums\PublishFailure;
use RuntimeException;

/**
 * Stops a Publish attempt with a known, user-safe failure code.
 */
final class PublishAborted extends RuntimeException
{
    public function __construct(public readonly PublishFailure $failure)
    {
        parent::__construct($failure->value);
    }
}
