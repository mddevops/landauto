<?php

namespace App\Support;

/**
 * Central phone normalization: `+7 (999) 111-22-33` → `79991112233`. The normalized form
 * (digits only, E.164 length) powers duplicate detection, blacklists and rate limits.
 * The Russian trunk prefix is the only national rewrite (owner-approved, X-021): exactly
 * 11 digits starting with 8 and written without «+» become 7XXXXXXXXXX. Numbers written
 * with «+» are already international and are never rewritten.
 */
final class PhoneNormalizer
{
    public const MIN_DIGITS = 10;

    public const MAX_DIGITS = 15;

    private const ALLOWED = '/^\+?[0-9 ().\-]+$/';

    public function normalize(?string $value): ?string
    {
        if ($value === null || $this->error($value) !== null) {
            return null;
        }

        $digits = (string) preg_replace('/\D/', '', $value);

        if (! str_starts_with(trim($value), '+') && strlen($digits) === 11 && $digits[0] === '8') {
            return '7'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Russian validation message, or null when the value is a valid phone number.
     */
    public function error(string $value): ?string
    {
        $value = trim($value);

        if (preg_match(self::ALLOWED, $value) !== 1) {
            return 'Телефон может содержать только цифры, пробелы, скобки, дефисы и «+» в начале.';
        }

        $digits = preg_match_all('/\d/', $value);

        return $digits < self::MIN_DIGITS || $digits > self::MAX_DIGITS
            ? 'Укажите телефон полностью, например +7 999 111-22-33.'
            : null;
    }
}
