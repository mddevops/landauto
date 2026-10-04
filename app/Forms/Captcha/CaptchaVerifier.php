<?php

namespace App\Forms\Captcha;

/**
 * Server-side CAPTCHA verification behind the public submission pipeline. Implementations
 * must never log or expose the visitor token or the server key.
 */
interface CaptchaVerifier
{
    /** Whether platform credentials are present, so a Site may require CAPTCHA. */
    public function isConfigured(): bool;

    /**
     * Public widget configuration for the browser: provider and public client key only.
     *
     * @return array{provider: string, client_key: string}
     */
    public function widget(): array;

    public function verify(string $token, ?string $ip): CaptchaVerdict;
}
