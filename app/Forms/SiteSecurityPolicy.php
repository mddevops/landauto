<?php

namespace App\Forms;

use App\Models\Site;

/**
 * Effective form security policy of a Site: stored overrides merged over config defaults.
 */
final readonly class SiteSecurityPolicy
{
    /** Allowed range per numeric setting. */
    public const BOUNDS = [
        'ip_limit' => [1, 1000],
        'ip_window_minutes' => [1, 1440],
        'phone_limit' => [1, 100],
        'phone_window_minutes' => [1, 1440],
        'duplicate_window_minutes' => [0, 1440],
    ];

    public function __construct(
        public int $ipLimit,
        public int $ipWindowMinutes,
        public int $phoneLimit,
        public int $phoneWindowMinutes,
        public int $duplicateWindowMinutes,
        public bool $captchaRequired,
    ) {}

    public static function forSite(Site $site): self
    {
        $values = self::values($site->form_security ?? []);

        return new self(
            $values['ip_limit'],
            $values['ip_window_minutes'],
            $values['phone_limit'],
            $values['phone_window_minutes'],
            $values['duplicate_window_minutes'],
            $values['captcha_required'],
        );
    }

    /**
     * Stored values that are present and in range win; everything else uses the defaults.
     *
     * @param  array<array-key, mixed>  $stored
     * @return array{ip_limit: int, ip_window_minutes: int, phone_limit: int, phone_window_minutes: int, duplicate_window_minutes: int, captcha_required: bool}
     */
    public static function values(array $stored): array
    {
        $number = function (string $key) use ($stored): int {
            [$min, $max] = self::BOUNDS[$key];
            $value = $stored[$key] ?? null;

            return is_int($value) && $value >= $min && $value <= $max ? $value : (int) config("forms.security.{$key}");
        };
        $captcha = $stored['captcha_required'] ?? null;

        return [
            'ip_limit' => $number('ip_limit'),
            'ip_window_minutes' => $number('ip_window_minutes'),
            'phone_limit' => $number('phone_limit'),
            'phone_window_minutes' => $number('phone_window_minutes'),
            'duplicate_window_minutes' => $number('duplicate_window_minutes'),
            'captcha_required' => is_bool($captcha) ? $captcha : (bool) config('forms.security.captcha_required'),
        ];
    }
}
