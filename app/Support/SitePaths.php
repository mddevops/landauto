<?php

namespace App\Support;

use App\Models\Site;
use App\Models\SiteSectionRedirect;

/**
 * First path segments of a published Site: Page slugs and section prefixes share one namespace
 * (ADR-011 §2). Previous section prefixes only redirect, so a Page or section may take them over.
 */
final class SitePaths
{
    public const PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public const PREFIX_MAX = 60;

    /**
     * Segments used by the public runtime or kept for future platform pages.
     */
    public const RESERVED = [
        'home', 'api', 'admin', 'assets', 'media', 'static', 'preview', 'sitemap', 'robots',
        'search', 'login', 'logout', 'register', 'account', 'cart', 'feed', 'rss', 'landflow',
    ];

    public static function isReserved(string $segment): bool
    {
        return in_array($segment, self::RESERVED, true);
    }

    public static function usedByPage(Site $site, string $segment): bool
    {
        return $site->pages()->where('slug', $segment)->exists();
    }

    public static function usedBySection(Site $site, string $segment, ?int $ignoreSectionId = null): bool
    {
        return $site->sections()
            ->where('prefix', $segment)
            ->when($ignoreSectionId !== null, fn ($query) => $query->whereKeyNot($ignoreSectionId))
            ->exists();
    }

    /**
     * A segment that becomes current stops redirecting.
     */
    public static function releaseRedirect(Site $site, string $segment): void
    {
        SiteSectionRedirect::query()->where('site_id', $site->id)->where('prefix', $segment)->delete();
    }
}
