<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordIfAvailable
{
    public function __construct(private readonly RequirePassword $requirePassword) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasPassword()) {
            return $next($request);
        }

        return $this->requirePassword->handle($request, $next);
    }
}
