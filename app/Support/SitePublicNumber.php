<?php

namespace App\Support;

use App\Models\Site;

/**
 * The Site's public address number on the Landflow public domain (D-127):
 * `{LANDFLOW_PUBLIC_DOMAIN}/{number}/…`. Twelve digits without a leading zero, assigned by the
 * platform once at creation, globally unique and immutable; customers never choose it.
 */
final class SitePublicNumber
{
    public const LENGTH = 12;

    /** Route requirement and validation pattern (without delimiters or anchors). */
    public const PATTERN = '[1-9][0-9]{11}';

    public static function isWellFormed(string $number): bool
    {
        return preg_match('/^'.self::PATTERN.'$/', $number) === 1;
    }

    public static function generate(): string
    {
        do {
            $number = (string) random_int(10 ** (self::LENGTH - 1), 10 ** self::LENGTH - 1);
        } while (Site::query()->where('public_number', $number)->exists());

        return $number;
    }
}
