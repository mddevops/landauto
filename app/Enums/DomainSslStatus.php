<?php

namespace App\Enums;

/** Certificate lifecycle reported by Landflow infrastructure; no key material is ever stored. */
enum DomainSslStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Failed = 'failed';
}
