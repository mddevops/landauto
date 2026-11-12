<?php

namespace App\Publishing\Runtime;

/**
 * Publish-time HTML and hydration payloads are rendered once but served on two kinds of address
 * (D-127): `{public_domain}/{number}/…` and the root of a custom domain. Every Site-relative URL
 * in a stored artifact starts with this token; the public runtime replaces it with the base path
 * of the address that serves the request. The token needs no escaping in HTML or JSON.
 */
final class PublicBasePath
{
    public const TOKEN = '__landflow_base__';

    public static function prefix(string $path): string
    {
        return self::TOKEN.$path;
    }

    public static function apply(string $content, string $basePath): string
    {
        return str_replace(self::TOKEN, $basePath, $content);
    }
}
