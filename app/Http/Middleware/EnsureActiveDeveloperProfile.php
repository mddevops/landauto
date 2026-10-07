<?php

namespace App\Http\Middleware;

use App\Developers\DeveloperAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Developer Platform gate: the authenticated User must own an active Developer Profile. The
 * resolved profile is exposed as the `developerProfile` request attribute.
 */
class EnsureActiveDeveloperProfile
{
    public const ATTRIBUTE = 'developerProfile';

    public function __construct(private DeveloperAccess $access) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $profile = $user instanceof User ? $this->access->activeProfile($user) : null;

        abort_if($profile === null, 403);

        $request->attributes->set(self::ATTRIBUTE, $profile);

        return $next($request);
    }
}
