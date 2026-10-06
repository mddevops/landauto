<?php

namespace App\Domains;

/**
 * Deterministic normalization and validation of customer hostnames (P7-001, SECURITY.md §53).
 * Only plain ASCII DNS hostnames are accepted: no scheme, path, port, wildcard, IP literal,
 * IDN/punycode or Landflow-owned host. There is no public-suffix heuristic, so apex and
 * subdomain hostnames are treated alike.
 */
final class CustomHostname
{
    public const MAX_LENGTH = 253;

    private const LABEL = '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/';

    public static function normalize(string $input): string
    {
        $host = strtolower(trim($input));

        return str_ends_with($host, '.') ? substr($host, 0, -1) : $host;
    }

    /**
     * Russian validation message for a normalized hostname, or null when it is acceptable.
     */
    public static function problem(string $host): ?string
    {
        if ($host === '') {
            return 'Укажите домен.';
        }

        if (preg_match('/[\x00-\x20\x7F]/', $host) === 1) {
            return 'Домен не должен содержать пробелы и служебные символы.';
        }

        if (preg_match('/[^\x00-\x7F]/', $host) === 1 || preg_match('/(^|\.)xn--/', $host) === 1) {
            return 'Домены с национальными символами пока не поддерживаются. Укажите домен латиницей, например dealer.ru.';
        }

        if (str_contains($host, '://') || preg_match('#[/?\#@:\\\\]#', $host) === 1) {
            return 'Укажите только домен без протокола, пути и порта, например dealer.ru.';
        }

        if (str_contains($host, '*')) {
            return 'Домены с подстановочным знаком «*» не поддерживаются.';
        }

        if (strlen($host) > self::MAX_LENGTH) {
            return 'Домен слишком длинный.';
        }

        $labels = explode('.', $host);

        if (count($labels) < 2) {
            return 'Укажите домен целиком, например dealer.ru.';
        }

        foreach ($labels as $label) {
            if (preg_match(self::LABEL, $label) !== 1) {
                return 'Домен указан неверно: каждая часть — от 1 до 63 латинских букв, цифр или дефисов, без дефиса по краям.';
            }
        }

        if (ctype_digit(end($labels))) {
            return 'IP-адрес нельзя подключить как домен.';
        }

        if (self::isReserved($host)) {
            return 'Этот адрес принадлежит Landflow и не может быть подключён как домен.';
        }

        return null;
    }

    /**
     * Landflow-owned and local hosts: the public domain and its subdomains, the application host,
     * the ingress CNAME target and localhost.
     */
    public static function isReserved(string $host): bool
    {
        $reserved = array_filter([
            'localhost',
            (string) config('publishing.public_domain'),
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            (string) config('domains.cname_target'),
        ]);

        foreach ($reserved as $owned) {
            if ($host === $owned || str_ends_with($host, '.'.$owned)) {
                return true;
            }
        }

        return false;
    }
}
