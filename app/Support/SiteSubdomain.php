<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Str;

/**
 * Landflow subdomain rules (`{label}.{LANDFLOW_PUBLIC_DOMAIN}`): one lowercase DNS label, globally
 * unique across all Sites (archived included), never a platform name. A Site gets a suggested
 * label once at creation; renaming the Site never changes it.
 */
final class SiteSubdomain
{
    /** Platform labels that never address a Site. */
    public const RESERVED = ['www', 'admin', 'api', 'app', 'support', 'static', 'assets', 'platform', 'auth', 'login', 'register'];

    public const MAX_LENGTH = 63;

    private const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    /** Generated labels stay short enough for a numeric suffix. */
    private const SUGGESTION_LENGTH = 40;

    public static function isWellFormed(string $label): bool
    {
        return preg_match(self::PATTERN, $label) === 1;
    }

    /**
     * Reserved platform names and IDN (punycode) labels, which browsers may display as look-alikes.
     */
    public static function isReserved(string $label): bool
    {
        return in_array($label, self::RESERVED, true) || str_starts_with($label, 'xn--');
    }

    public static function isTaken(string $label, ?Site $except = null): bool
    {
        return Site::query()
            ->where('subdomain', $label)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->exists();
    }

    /**
     * A free label derived from the Site name: transliterated, `-2`, `-3`… on collisions.
     */
    public static function suggest(string $name): string
    {
        $base = trim(Str::limit(Str::slug($name, '-', 'ru'), self::SUGGESTION_LENGTH, ''), '-');

        if (! self::isWellFormed($base) || self::isReserved($base)) {
            $base = $base === '' || ! self::isWellFormed($base) ? 'site' : $base.'-site';
        }

        $candidate = $base;

        for ($suffix = 2; self::isTaken($candidate); $suffix++) {
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }
}
