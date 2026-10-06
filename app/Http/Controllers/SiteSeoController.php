<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Site;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Support\DesignerScope;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «SEO» of a Site: Draft SEO of every Page in one place (same fields and permissions as in the
 * designer) plus the public addresses search engines use. Changes go live with the next Publish.
 */
class SiteSeoController extends Controller
{
    public function __invoke(DesignerScope $scope, Site $site): Response
    {
        $scope->site($site);
        Gate::authorize('editSeo', $site);

        $published = $site->active_published_version_id !== null;

        return Inertia::render('sites/seo', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'pages' => $site->pages()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'public_id', 'site_id', 'title', 'slug', 'is_home', 'seo_title', 'seo_description', 'seo_noindex'])
                ->map(fn (Page $page): array => [
                    'public_id' => $page->public_id,
                    'title' => $page->title,
                    'path' => $page->is_home ? '/' : '/'.$page->slug,
                    'seo_title' => $page->seo_title,
                    'seo_description' => $page->seo_description,
                    'seo_noindex' => $page->seo_noindex,
                ])
                ->values()
                ->all(),
            'addresses' => [
                'primary' => PublicSiteResolver::primaryUrl($site),
                'sitemap' => $published ? PublicSiteResolver::primaryUrl($site, '/sitemap.xml') : null,
                'robots' => $published ? PublicSiteResolver::primaryUrl($site, '/robots.txt') : null,
            ],
            'published' => $published,
            'limits' => ['title' => 120, 'description' => 300],
            'can' => ['editIndexing' => Gate::allows('editSeoIndexing', $site)],
        ]);
    }
}
