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

    public function label(): string
    {
        return match ($this) {
            self::MaxSites => 'Количество сайтов',
            self::MaxMembers => 'Количество участников',
            self::CustomDomain => 'Собственный домен',
            self::RemoveBranding => 'Без брендинга Landflow',
            self::MultiPageSites => 'Многостраничные сайты',
        };
    }

    /** Boolean keys retained only for reading legacy D-121 catalog rows during rollback windows. @return list<self> */
    public static function catalogGates(): array
    {
        return array_values(array_filter(self::cases(), fn (self $entitlement): bool => $entitlement->valueType() === EntitlementValueType::Boolean));
    }
}
