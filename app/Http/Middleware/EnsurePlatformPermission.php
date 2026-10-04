<?php

namespace App\Http\Middleware;

use App\Enums\PlatformPermission;
use App\Models\User;
use App\Support\PlatformAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformPermission
{
    public function __construct(private PlatformAuthorization $authorization) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $this->authorization->allows($user, PlatformPermission::from($permission)),
            403,
        );

        return $next($request);
    }
}
