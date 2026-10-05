<?php

namespace App\Publishing\Runtime;

use App\Enums\SiteStatus;
use App\Models\Site;

/**
 * Maps a public request host to its Site (ADR-006 §9). Only Landflow subdomains of the configured
 * public domain exist today; custom domains would be resolved here as well, without touching the
 * runtime controllers.
 */
final class PublicSiteResolver
{
    /** Platform labels that never address a Site. */
    public const RESERVED = ['www', 'admin', 'api', 'app', 'support', 'static', 'assets', 'platform', 'auth', 'login', 'register'];

    /**
     * Route requirement for the subdomain host parameter: one DNS label, not reserved. The
     * lookahead ends with the dot that follows the label inside the host pattern.
     */
    public static function routePattern(): string
    {
        return '(?!(?:'.implode('|', self::RESERVED).')\.)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
    }

    public function resolve(string $host): ?Site
    {
        $label = self::subdomainOf($host);

        if ($label === null || in_array($label, self::RESERVED, true)) {
            return null;
        }

        return Site::query()
            ->where('subdomain', $label)
            ->where('status', SiteStatus::Active->value)
            ->first();
    }

    public static function subdomainOf(string $host): ?string
    {
        $host = strtolower(rtrim($host, '.'));
        $suffix = '.'.config('publishing.public_domain');

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));

        return $label !== '' && ! str_contains($label, '.') ? $label : null;
    }

    public static function url(Site $site, string $path = '/'): ?string
    {
        if ($site->subdomain === null) {
            return null;
        }

        $port = config('publishing.public_port');

        return config('publishing.public_scheme').'://'.$site->subdomain.'.'.config('publishing.public_domain')
            .(filled($port) ? ':'.$port : '').$path;
    }
}
