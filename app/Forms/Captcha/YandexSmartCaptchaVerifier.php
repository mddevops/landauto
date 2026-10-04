<?php

namespace App\Forms\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Yandex SmartCaptcha server validation. Only `status` drives the verdict (`message` is
 * diagnostic per the provider documentation). Network errors and non-200 responses fail
 * open by owner decision and are logged without the token, key or visitor IP.
 */
final class YandexSmartCaptchaVerifier implements CaptchaVerifier
{
    public const VALIDATE_URL = 'https://smartcaptcha.cloud.yandex.ru/validate';

    public function __construct(
        private HttpFactory $http,
        private ?string $clientKey,
        private ?string $serverKey,
        private int $timeoutSeconds = 3,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->clientKey) && filled($this->serverKey);
    }

    public function widget(): array
    {
        return ['provider' => 'yandex', 'client_key' => (string) $this->clientKey];
    }

    public function verify(string $token, ?string $ip): CaptchaVerdict
    {
        try {
            $response = $this->http->asForm()
                ->acceptJson()
                ->connectTimeout($this->timeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->post(self::VALIDATE_URL, array_filter([
                    'secret' => (string) $this->serverKey,
                    'token' => $token,
                    'ip' => $ip,
                ], fn (?string $value): bool => $value !== null && $value !== ''));
        } catch (ConnectionException) {
            return $this->unavailable(null);
        }

        if ($response->status() !== 200) {
            return $this->unavailable($response->status());
        }

        $status = $response->json('status');

        return match ($status) {
            'ok' => CaptchaVerdict::Passed,
            'failed' => CaptchaVerdict::Failed,
            default => $this->unavailable($response->status()),
        };
    }

    private function unavailable(?int $httpStatus): CaptchaVerdict
    {
        Log::warning('Yandex SmartCaptcha validation unavailable; submission accepted without CAPTCHA verification.', [
            'http_status' => $httpStatus,
        ]);

        return CaptchaVerdict::Unavailable;
    }
}
