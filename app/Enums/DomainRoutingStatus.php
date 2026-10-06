<?php

namespace App\Enums;

/** Whether the hostname's A/AAAA/CNAME records reach the Landflow ingress. */
enum DomainRoutingStatus: string
{
    case Pending = 'pending';
    case Misconfigured = 'misconfigured';
    case Verified = 'verified';
}
