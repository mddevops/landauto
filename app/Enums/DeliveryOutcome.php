<?php

namespace App\Enums;

/**
 * Normalized result of one adapter call. Transient failures may be retried; permanent failures
 * (invalid configuration, rejected credentials, rejected payload) never loop.
 */
enum DeliveryOutcome: string
{
    case Success = 'success';
    case TransientFailure = 'transient_failure';
    case PermanentFailure = 'permanent_failure';
}
