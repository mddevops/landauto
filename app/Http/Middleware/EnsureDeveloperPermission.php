<?php

namespace App\Http\Middleware;

use App\Developers\DeveloperAuthorization;
use App\Enums\DeveloperPermission;
use App\Models\DeveloperProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Developer creator permission gate (D-118). Runs after `EnsureActiveDeveloperProfile`, so it only
 * checks the current User's own active profile.
 */
class EnsureDeveloperPermission
{
    public function __construct(private DeveloperAuthorization $authorization) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $profile = $request->attributes->get(EnsureActiveDeveloperProfile::ATTRIBUTE);

        abort_unless(
            $profile instanceof DeveloperProfile && $this->authorization->allows($profile, DeveloperPermission::from($permission)),
            403,
        );

        return $next($request);
    }
}
