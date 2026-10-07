<?php

namespace App\Enums;

enum Entitlement: string
{
    case MaxSites = 'max_sites';
    case MaxMembers = 'max_members';
    case CustomDomain = 'custom_domain';
    case RemoveBranding = 'remove_branding';
    case MultiPageSites = 'multi_page_sites';

    public function valueType(): EntitlementValueType
    {
        return match ($this) {
            self::MaxSites, self::MaxMembers => EntitlementValueType::Integer,
            self::CustomDomain, self::RemoveBranding, self::MultiPageSites => EntitlementValueType::Boolean,
        };
    }
}
