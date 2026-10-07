<?php

namespace App\Enums;

use App\Support\Money;

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

    /**
     * Exactly the fields of the mode: a boolean catalog-gate entitlement for `entitlement`, a
     * positive price in a supported currency for `paid`, nothing otherwise.
     */
    public static function fieldsMatch(mixed $mode, mixed $entitlement, ?int $priceMinor, ?string $currency): bool
    {
        return match ($mode instanceof self ? $mode : null) {
            self::Entitlement => $entitlement instanceof Entitlement
                && in_array($entitlement, Entitlement::catalogGates(), true)
                && $priceMinor === null && $currency === null,
            self::Paid => $entitlement === null && $priceMinor !== null && $priceMinor > 0
                && $currency !== null && Money::supports($currency),
            self::Free, self::AdminGrant => $entitlement === null && $priceMinor === null && $currency === null,
            null => false,
        };
    }
}
