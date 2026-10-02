<?php

namespace App\Exceptions;

use RuntimeException;

class SiteLimitReachedException extends RuntimeException
{
    // Raised when the current Workspace has no remaining active Site slots.
}
