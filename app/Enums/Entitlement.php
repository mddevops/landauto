<?php

namespace App\Enums;

enum Entitlement: string
{
    case MaxSites = 'max_sites';
    case MaxMembers = 'max_members';
    case CustomDomain = 'custom_domain';
    case RemoveBranding = 'remove_branding';

    public function valueType(): EntitlementValueType
    {
        return match ($this) {
            self::MaxSites, self::MaxMembers => EntitlementValueType::Integer,
            self::CustomDomain, self::RemoveBranding => EntitlementValueType::Boolean,
        };
    }
}
