<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A Site type / start / Template combination the backend refuses (D-119); carries a Russian message
 * for the given form field.
 */
class SiteCreationRejectedException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
