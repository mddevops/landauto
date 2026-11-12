<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Publishing\Runtime\PublicSiteResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the Site of a public address for every published-runtime route: by public number on
 * the Landflow public domain, by host on a custom domain (D-127, D-111). Requests on a
 * non-primary address get a 301 to the primary address with the Site-relative path and query
 * preserved. Unknown addresses get the same safe 404 as missing pages; every response is marked
 * `nosniff`.
 */
class ResolvePublicSite
{
    public function __construct(private PublicSiteResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $number = $request->route()?->parameter('publicNumber');
        $request->route()?->forgetParameter('publicNumber');
        $request->route()?->forgetParameter('host');
        $match = is_string($number) ? $this->resolver->matchNumber($number) : $this->resolver->matchHost($request->getHost());

        if ($match === null) {
            $response = self::notFound();
        } elseif ($match->redirectBase !== null) {
            $response = redirect()->away($match->redirectBase.self::sitePath($request->getRequestUri(), $match->basePath), 301);
        } else {
            $request->attributes->set('publicSite', $match->site);
            $request->attributes->set('publicBasePath', $match->basePath);
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

    /**
     * Base path of the address serving this request: '/{number}' on the public domain, '' on a
     * custom domain.
     */
    public static function basePath(Request $request): string
    {
        $base = $request->attributes->get('publicBasePath');

        return is_string($base) ? $base : '';
    }

    public static function notFound(): Response
    {
        return response()->view('published.not-found', [], 404);
    }

    /**
     * The request URI relative to the Site: base path removed, always starting with '/'.
     */
    private static function sitePath(string $requestUri, string $basePath): string
    {
        $path = substr($requestUri, strlen($basePath));

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }
}
