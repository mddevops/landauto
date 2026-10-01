<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class ThrottleFortifyRoute
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = match ($request->route()?->getName()) {
            'register.store' => 'registration',
            'password.email' => 'password-email',
            'password.update' => 'password-reset',
            'password.confirm.store' => 'password-confirm',
            default => null,
        };

        return $limiter === null
            ? $next($request)
            : $this->throttle->handle($request, $next, $limiter);
    }
}
