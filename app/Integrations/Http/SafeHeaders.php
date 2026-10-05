<?php

namespace App\Integrations\Http;

/**
 * Header names customers may configure for outbound integration requests. Transport, proxy and
 * cookie headers are owned by the HTTP client; Authorization is built only from encrypted
 * credentials; Idempotency-Key and Content-Type are set by Landflow.
 */
final class SafeHeaders
{
    public const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9-]{0,63}$/';

    public const FORBIDDEN = [
        'host',
        'content-length',
        'transfer-encoding',
        'connection',
        'keep-alive',
        'upgrade',
        'te',
        'trailer',
        'proxy-authorization',
        'proxy-authenticate',
        'proxy-connection',
        'authorization',
        'cookie',
        'set-cookie',
        'content-type',
        'idempotency-key',
        'expect',
        'forwarded',
        'x-forwarded-for',
        'x-forwarded-host',
        'x-real-ip',
    ];

    public static function isAllowedName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1
            && ! in_array(strtolower($name), self::FORBIDDEN, true);
    }

    public static function isAllowedValue(string $value): bool
    {
        return strlen($value) <= 255 && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }
}
