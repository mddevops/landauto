<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * ADR-004 money helper: integer minor units, ISO 4217 currency, no floats.
 */
final class Money
{
    /**
     * Supported currencies and their minor-unit exponent.
     */
    public const CURRENCIES = ['RUB' => 2];

    public const DEFAULT_CURRENCY = 'RUB';

    /**
     * Upper bound that fits BIGINT on every supported driver (one quadrillion minor units).
     */
    public const MAX_MINOR = 1_000_000_000_000_000;

    private const SYMBOLS = ['RUB' => '₽'];

    /**
     * Parse a human decimal string ("1 850 000", "1850000,50") into minor units.
     * Returns null when the input is not a valid non-negative amount for the currency.
     */
    public static function parse(string $input, string $currency = self::DEFAULT_CURRENCY): ?int
    {
        $exponent = self::exponent($currency);
        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim($input));

        if (preg_match('/^(\d{1,16})(?:\.(\d{1,'.$exponent.'}))?$/', $normalized, $matches) !== 1) {
            return null;
        }

        $major = ltrim($matches[1], '0');
        $minor = str_pad($matches[2] ?? '', $exponent, '0');
        $digits = ltrim($major.$minor, '0');

        if ($digits === '') {
            return 0;
        }

        if (strlen($digits) > strlen((string) self::MAX_MINOR)
            || (strlen($digits) === strlen((string) self::MAX_MINOR) && strcmp($digits, (string) self::MAX_MINOR) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * Decimal string for form inputs ("1850000.5" → "1850000,50"); never a float.
     */
    public static function toInput(int $minor, string $currency = self::DEFAULT_CURRENCY): string
    {
        $exponent = self::exponent($currency);
        $digits = str_pad((string) $minor, $exponent + 1, '0', STR_PAD_LEFT);
        $major = substr($digits, 0, -$exponent);
        $fraction = substr($digits, -$exponent);

        return $fraction === str_repeat('0', $exponent) ? $major : "{$major},{$fraction}";
    }

    /**
     * Russian display format: "1 850 000 ₽", kopecks only when present.
     */
    public static function format(int $minor, string $currency = self::DEFAULT_CURRENCY): string
    {
        $input = self::toInput($minor, $currency);
        [$major, $fraction] = array_pad(explode(',', $input, 2), 2, null);
        $grouped = (string) preg_replace('/\B(?=(\d{3})+(?!\d))/', "\u{00A0}", $major);
        $symbol = self::SYMBOLS[$currency] ?? $currency;

        return ($fraction === null ? $grouped : "{$grouped},{$fraction}")."\u{00A0}{$symbol}";
    }

    public static function supports(string $currency): bool
    {
        return array_key_exists($currency, self::CURRENCIES);
    }

    private static function exponent(string $currency): int
    {
        return self::CURRENCIES[$currency] ?? throw new InvalidArgumentException("Unsupported currency [{$currency}].");
    }
}
