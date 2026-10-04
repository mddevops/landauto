<?php

namespace App\Forms\Captcha;

/**
 * Deterministic verifier for automated tests and E2E; never bound outside the
 * `testing`/`e2e` environments.
 */
final class FakeCaptchaVerifier implements CaptchaVerifier
{
    public const PASS_TOKEN = 'fake-captcha-pass';

    public const OUTAGE_TOKEN = 'fake-captcha-outage';

    public function isConfigured(): bool
    {
        return true;
    }

    public function widget(): array
    {
        return ['provider' => 'fake', 'client_key' => 'fake'];
    }

    public function verify(string $token, ?string $ip): CaptchaVerdict
    {
        return match ($token) {
            self::PASS_TOKEN => CaptchaVerdict::Passed,
            self::OUTAGE_TOKEN => CaptchaVerdict::Unavailable,
            default => CaptchaVerdict::Failed,
        };
    }
}
