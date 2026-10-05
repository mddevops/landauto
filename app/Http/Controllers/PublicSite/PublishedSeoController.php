<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicSite;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Publishing\Runtime\PublishedPages;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/sitemap.xml` and `/robots.txt` of a published Site, built only from the active Published
 * Version: indexable published Pages, never Draft or application URLs.
 */
class PublishedSeoController extends Controller
{
    public function sitemap(Request $request, PublishedPages $pages): Response
    {
        $site = ResolvePublicSite::site($request);
        $version = $pages->activeVersion($site);

        if ($version === null) {
            return ResolvePublicSite::notFound();
        }

        return response()
            ->view('published.sitemap', [
                'urls' => array_map(fn (string $path): string => (string) PublicSiteResolver::url($site, $path), $pages->indexablePaths($site, $version)),
                'lastmod' => $version->ready_at?->toAtomString(),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(Request $request, PublishedPages $pages): Response
    {
        $site = ResolvePublicSite::site($request);

        if ($pages->activeVersion($site) === null) {
            return ResolvePublicSite::notFound();
        }

        $lines = [
            'User-agent: *',
            'Disallow: /_landflow/forms/',
            '',
            'Sitemap: '.PublicSiteResolver::url($site, '/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
