<?php

namespace App\Publishing\Runtime;

/**
 * CSP for the cookie-less published Site origin (ADR-009 §4 / X-029). Native JavaScript may load
 * only immutable same-origin modules; external origins are enabled only for active first-party
 * integrations. `unsafe-inline` is required because opaque-origin legacy `srcdoc` frames inherit
 * the parent policy; their authored scripts remain isolated by `sandbox="allow-scripts"` and their
 * own stricter CSP. `unsafe-eval` and arbitrary network origins remain forbidden.
 */
final class PublicContentSecurityPolicy
{
    public function header(bool $metrica, bool $smartCaptcha): string
    {
        $script = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];
        $images = ["'self'", 'data:', 'blob:'];
        $frames = [];

        if ($metrica) {
            $script[] = 'https://mc.yandex.ru';
            $connect[] = 'https://mc.yandex.ru';
            $images[] = 'https://mc.yandex.ru';
            $frames[] = 'https://mc.yandex.ru';
        }

        if ($smartCaptcha) {
            $script[] = 'https://smartcaptcha.cloud.yandex.ru';
            $connect[] = 'https://smartcaptcha.cloud.yandex.ru';
            $images[] = 'https://smartcaptcha.cloud.yandex.ru';
            $frames[] = 'https://smartcaptcha.cloud.yandex.ru';
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'none'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            'script-src '.implode(' ', $script),
            "style-src 'self' 'unsafe-inline'",
            'img-src '.implode(' ', $images),
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connect),
            'frame-src '.($frames === [] ? "'none'" : implode(' ', $frames)),
            "worker-src 'none'",
        ]);
    }
}
