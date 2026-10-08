<?php

namespace App\Enums;

use App\Support\Money;

/**
 * How a customer Site may use a published catalog item (D-121). Entitlements and catalog licenses
 * stay separate: a license for the Site or its Workspace always grants access; an entitlement
 * grants access only in `entitlement` mode.
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
     * Exactly the fields of the mode: a boolean catalog-gate entitlement for `entitlement`; for
     * `paid` a Site and / or Workspace license price (each positive when set, at least one) in a
     * supported currency (D-121); nothing otherwise.
     */
    public static function fieldsMatch(mixed $mode, mixed $entitlement, ?int $sitePriceMinor, ?int $workspacePriceMinor, ?string $currency): bool
    {
        $noPrices = $sitePriceMinor === null && $workspacePriceMinor === null && $currency === null;

        return match ($mode instanceof self ? $mode : null) {
            self::Entitlement => $entitlement instanceof Entitlement
                && in_array($entitlement, Entitlement::catalogGates(), true)
                && $noPrices,
            self::Paid => $entitlement === null
                && ($sitePriceMinor !== null || $workspacePriceMinor !== null)
                && ($sitePriceMinor === null || $sitePriceMinor > 0)
                && ($workspacePriceMinor === null || $workspacePriceMinor > 0)
                && $currency !== null && Money::supports($currency),
            self::Free, self::AdminGrant => $entitlement === null && $noPrices,
            null => false,
        };
    }
}
