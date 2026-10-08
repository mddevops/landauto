<?php

namespace App\Enums;

/**
 * Where a catalog license came from (D-121), independent of its scope. `purchase` needs the
 * billing integration (P10-005) and is never created yet; `admin_grant` licenses are granted by a
 * Super Admin.
 */
enum CatalogLicenseSource: string
{
    case Purchase = 'purchase';
    case AdminGrant = 'admin_grant';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Покупка',
            self::AdminGrant => 'Выдана администратором',
        };
    }
}
