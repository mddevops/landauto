<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ValidateFortifyEmail
{
    /**
     * Reject structured email input before Fortify normalizes it as a string.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->usesEmail($request) && $request->has('email') && ! is_string($request->input('email'))) {
            throw ValidationException::withMessages([
                'email' => __('validation.string', ['attribute' => __('validation.attributes.email')]),
            ]);
        }

        return $next($request);
    }

    private function usesEmail(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'login.store',
            'register.store',
            'password.email',
            'password.update',
        ], true);
    }
}
