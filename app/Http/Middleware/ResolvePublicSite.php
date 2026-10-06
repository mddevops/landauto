<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Publishing\Runtime\PublicSiteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Site of a public host for every published-runtime route. Requests on a non-primary
 * address (Landflow subdomain or alternate custom domain while another host is primary) get a 301
 * to the primary host with path and query preserved. Unknown hosts get the same safe 404 as
 * missing pages; every response is marked `nosniff`.
 */
class ResolvePublicSite
{
    public function __construct(private PublicSiteResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->route()?->forgetParameter('subdomain');
        $request->route()?->forgetParameter('host');
        $match = $this->resolver->match($request->getHost());

        if ($match === null) {
            $response = self::notFound();
        } elseif ($match->redirectHost !== null) {
            $response = redirect()->away(PublicSiteResolver::hostUrl($match->redirectHost, $request->getRequestUri()), 301);
        } else {
            $request->attributes->set('publicSite', $match->site);
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
