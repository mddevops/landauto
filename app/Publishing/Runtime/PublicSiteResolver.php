<?php

namespace App\Publishing\Runtime;

use App\Domains\CustomDomainAccess;
use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Support\SiteSubdomain;

/**
 * Maps a public request host to its Site (ADR-006 §9, D-111). Custom domains are looked up first,
 * then Landflow subdomains of the configured public domain. A custom domain is served only while
 * it is fully active (ownership, routing, SSL) and its Workspace keeps the `custom_domain`
 * entitlement; otherwise the Landflow subdomain is the Site's primary address.
 */
final class PublicSiteResolver
{
    /**
     * Route requirement for the subdomain host parameter: one DNS label, not reserved. The
     * lookahead ends with the dot that follows the label inside the host pattern.
     */
    public static function routePattern(): string
    {
        return '(?!(?:'.implode('|', SiteSubdomain::RESERVED).')\.)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
    }

    /**
     * Route requirement for custom hostnames: dotted DNS names that are not the application host,
     * the public domain or any of its subdomains, and not IPv4 literals. Application routes keep
     * every excluded host.
     */
    public static function customHostPattern(): string
    {
        $public = preg_quote((string) config('publishing.public_domain'), '#');
        $excluded = array_values(array_filter(array_unique([
            (string) config('publishing.public_domain'),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            'localhost',
        ])));

        return '(?!(?:'.implode('|', array_map(fn (string $host): string => preg_quote($host, '#'), $excluded)).')$)'
            .'(?!.*\.'.$public.'$)'
            .'(?![0-9.]+$)'
            .'(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}';
    }

    public function match(string $host): ?PublicSiteMatch
    {
        $host = strtolower(rtrim($host, '.'));
        $label = self::subdomainOf($host);

        if ($label !== null) {
            $site = $this->resolve($host);

            return $site === null ? null : new PublicSiteMatch($site, self::primaryDomain($site)?->hostname);
        }

        $domain = SiteDomain::query()->with('site.workspace')->where('hostname', $host)->first();

        if ($domain === null || ! $domain->isActive() || $domain->site->status !== SiteStatus::Active
            || ! app(CustomDomainAccess::class)->entitled($domain->site)) {
            return null;
        }

        $primary = self::primaryDomain($domain->site);

        if ($primary?->is($domain) === true) {
            return new PublicSiteMatch($domain->site, null);
        }

        return new PublicSiteMatch($domain->site, $primary !== null ? $primary->hostname : self::subdomainHost($domain->site));
    }

    public function resolve(string $host): ?Site
    {
        $label = self::subdomainOf($host);

        if ($label === null || SiteSubdomain::isReserved($label)) {
            return null;
        }

        return Site::query()
            ->where('subdomain', $label)
            ->where('status', SiteStatus::Active->value)
            ->first();
    }

    /**
     * The custom primary domain currently in effect: marked primary, fully active and entitled.
     */
    public static function primaryDomain(Site $site): ?SiteDomain
    {
        $domain = $site->domains()->where('is_primary', true)->first();

        return $domain !== null && $domain->isActive() && app(CustomDomainAccess::class)->entitled($site) ? $domain : null;
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

    /**
     * Landflow subdomain address; always works, even when a custom domain is primary.
     */
    public static function url(Site $site, string $path = '/'): ?string
    {
        $host = self::subdomainHost($site);

        return $host === null ? null : self::hostUrl($host, $path);
    }

    /**
     * Address visitors should use: the custom primary domain if one is in effect, else the
     * Landflow subdomain. Canonical URLs, sitemap and robots use it.
     */
    public static function primaryUrl(Site $site, string $path = '/'): ?string
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

    private static function subdomainHost(Site $site): ?string
    {
        return $site->subdomain === null ? null : $site->subdomain.'.'.config('publishing.public_domain');
    }
}
