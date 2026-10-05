<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Publishing\Runtime\PublicSiteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Site of a public host for every published-runtime route. Unknown hosts get the
 * same safe 404 as missing pages; every response is marked `nosniff`.
 */
class ResolvePublicSite
{
    public function __construct(private PublicSiteResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->route()?->forgetParameter('subdomain');
        $site = $this->resolver->resolve($request->getHost());

        if ($site === null) {
            $response = self::notFound();
        } else {
            $request->attributes->set('publicSite', $site);
            $response = $next($request);
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public static function site(Request $request): Site
    {
        $site = $request->attributes->get('publicSite');
        abort_unless($site instanceof Site, 404);

        return $site;
    }

    public static function notFound(): Response
    {
        return response()->view('published.not-found', [], 404);
    }
}
