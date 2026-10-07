<?php

namespace App\Enums;

/**
 * How a customer Site may use a published catalog item (D-079). Entitlements and Site licenses
 * stay separate: a license for the Site always grants access; an entitlement grants access only
 * in `entitlement` mode.
 */
enum CatalogAccessMode: string
{
    case Free = 'free';
    case Entitlement = 'entitlement';
    case Paid = 'paid';
    case AdminGrant = 'admin_grant';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Бесплатно',
            self::Entitlement => 'По тарифу',
            self::Paid => 'Платно',
            self::AdminGrant => 'Выдаёт администратор',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $mode): array => ['value' => $mode->value, 'label' => $mode->label()], self::cases());
    }
}
