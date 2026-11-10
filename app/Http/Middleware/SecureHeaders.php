<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for the authenticated Landflow application (X-013).
 * Published Site hosts keep their own CSP via the public runtime.
 */
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('X-Frame-Options', 'DENY', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()', false);

        if ($request->secure() && (app()->environment('production') || config('security.force_https'))) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.max(0, (int) config('security.hsts_max_age')).';includeSubDomains',
                false,
            );
        }

        return $response;
    }
}
