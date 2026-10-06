<?php

namespace App\Enums;

/** Ownership of a custom hostname, proven only by the exact TXT record. */
enum DomainVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Failed = 'failed';
}
