<?php

namespace App\Publishing\Runtime;

use App\Domains\CustomDomainAccess;
use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\SiteDomain;

/**
 * Maps a public request to its Site (D-127, D-111). On the Landflow public domain the Site is
 * addressed by its public number in the first path segment: `{public_domain}/{number}/…`. Custom
 * domains are addressed by the request host and serve the Site at their root. A custom domain is
 * served only while it is fully active (ownership, routing, SSL) and its Workspace keeps the
 * `custom_domain` entitlement; otherwise the Landflow address is the Site's primary address.
 */
final class PublicSiteResolver
{
    /**
     * End of the host inside a host route requirement. Route caching compiles host and path into
     * one subject (`{host}.{path}`), so a plain `$` would never match there.
     */
    private const HOST_END = '(?:$|\./)';

    /**
     * Route requirement for custom hostnames: dotted DNS names that are not the application host,
     * the public domain or any of its subdomains, and not IPv4 literals. Application routes keep
     * every excluded host.
     */
    public static function customHostPattern(): string
    {
        $public = preg_quote(self::publicDomain(), '#');
        $excluded = array_values(array_filter(array_unique([
            self::publicDomain(),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            'localhost',
        ])));

        return '(?!(?:'.implode('|', array_map(fn (string $host): string => preg_quote($host, '#'), $excluded)).')'.self::HOST_END.')'
            .'(?![^/]*\.'.$public.self::HOST_END.')'
            .'(?![0-9.]+(?:$|/))'
            .'(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}';
    }

    /**
     * Whether published Sites share the application host (local development and tests only;
     * production refuses it). The public domain then has no catch-all of its own.
     */
    public static function sharesApplicationHost(): bool
    {
        return self::publicDomain() === strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /**
     * An active Site addressed by its public number on the Landflow public domain.
     */
    public function matchNumber(string $number): ?PublicSiteMatch
    {
        $site = Site::query()
            ->where('public_number', $number)
            ->where('status', SiteStatus::Active->value)
            ->first();

        if ($site === null) {
            return null;
        }

        $primary = self::primaryDomain($site);

        return new PublicSiteMatch($site, self::basePath($site), $primary === null ? null : self::hostUrl($primary->hostname, ''));
    }

    /**
     * An active Site addressed by one of its custom domains.
     */
    public function matchHost(string $host): ?PublicSiteMatch
    {
        $host = strtolower(rtrim($host, '.'));
        $domain = SiteDomain::query()->with('site.workspace')->where('hostname', $host)->first();

        if ($domain === null || ! $domain->isActive() || $domain->site->status !== SiteStatus::Active
            || ! app(CustomDomainAccess::class)->entitled($domain->site)) {
            return null;
        }

        $primary = self::primaryDomain($domain->site);

        if ($primary?->is($domain) === true) {
            return new PublicSiteMatch($domain->site, '', null);
        }

        return new PublicSiteMatch($domain->site, '', $primary !== null ? self::hostUrl($primary->hostname, '') : self::url($domain->site, ''));
    }

    /**
     * The custom primary domain currently in effect: marked primary, fully active and entitled.
     */
    public static function primaryDomain(Site $site): ?SiteDomain
    {
        $domain = $site->domains()->where('is_primary', true)->first();

        return $domain !== null && $domain->isActive() && app(CustomDomainAccess::class)->entitled($site) ? $domain : null;
    }

    public static function basePath(Site $site): string
    {
        return '/'.$site->public_number;
    }

    /**
     * Landflow address; always works, even when a custom domain is primary.
     */
    public static function url(Site $site, string $path = '/'): string
    {
        return self::hostUrl(self::publicDomain(), self::basePath($site).$path);
    }

    /**
     * Address visitors should use: the custom primary domain if one is in effect, else the
     * Landflow address. Canonical URLs, sitemap and robots use it.
     */
    public static function primaryUrl(Site $site, string $path = '/'): string
    {
        $primary = self::primaryDomain($site);

        return $primary === null ? self::url($site, $path) : self::hostUrl($primary->hostname, $path);
    }

    public static function hostUrl(string $host, string $path = '/'): string
    {
        return self::scheme().'://'.$host.self::portSuffix().$path;
    }

    public static function scheme(): string
    {
        return (string) config('publishing.public_scheme');
    }

    public static function portSuffix(): string
    {
        $port = config('publishing.public_port');

        return filled($port) ? ':'.$port : '';
    }

    private static function publicDomain(): string
    {
        return strtolower((string) config('publishing.public_domain'));
    }
}
