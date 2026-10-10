<?php

namespace App\Enums;

use App\Support\Money;

/**
 * How a customer Site may acquire a published catalog item. In `entitlement` mode, X-026 maps the
 * item explicitly to eligible Plans; Plan entitlements remain separate product capabilities.
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
     * Exactly the fields of the mode: no scalar entitlement for `entitlement` (Plan inclusion is
     * relational); for `paid` a Site and / or Workspace license price (each positive when set, at least one) in a
     * supported currency (D-121); nothing otherwise.
     */
    public static function fieldsMatch(mixed $mode, mixed $entitlement, ?int $sitePriceMinor, ?int $workspacePriceMinor, ?string $currency): bool
    {
        $noPrices = $sitePriceMinor === null && $workspacePriceMinor === null && $currency === null;

        return match ($mode instanceof self ? $mode : null) {
            // Keep valid legacy entitlement values readable for rollback safety; X-026 access is
            // resolved exclusively through the explicit Plan pivot.
            self::Entitlement => ($entitlement === null
                || ($entitlement instanceof Entitlement && in_array($entitlement, Entitlement::catalogGates(), true))) && $noPrices,
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
