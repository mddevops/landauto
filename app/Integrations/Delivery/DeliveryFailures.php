<?php

namespace App\Integrations\Delivery;

/**
 * Retry classification shared by every HTTP adapter and Test Connection (FORMS_AND_INTEGRATIONS.md §27).
 * Timeouts, network/DNS failures, 408, 425, 429, 500, 502, 503 and 504 are transient; rejected
 * credentials, rejected payloads, redirects and other statuses are permanent.
 */
final class DeliveryFailures
{
    public const TRANSIENT_STATUSES = [408, 425, 429, 500, 502, 503, 504];

    public const TEMPORARY_MESSAGE = 'Сервис временно недоступен. Доставка будет повторена.';

    public static function forHttpStatus(int $status, ?int $latencyMs = null): DeliveryResult
    {
        if ($status >= 200 && $status < 300) {
            return DeliveryResult::success($status, latencyMs: $latencyMs);
        }

        if (in_array($status, self::TRANSIENT_STATUSES, true)) {
            return DeliveryResult::transient("http_{$status}", self::TEMPORARY_MESSAGE, $status, latencyMs: $latencyMs);
        }

        [$code, $message] = match (true) {
            $status >= 300 && $status < 400 => ['redirect_not_followed', 'Сервис ответил перенаправлением. Укажите конечный адрес.'],
            $status === 401, $status === 403 => ["http_{$status}", 'Сервис отклонил авторизацию. Проверьте доступ в профиле интеграции.'],
            $status === 404 => ['http_404', 'Адрес назначения не найден.'],
            $status >= 400 && $status < 500 => ["http_{$status}", 'Сервис отклонил данные заявки.'],
            default => ["http_{$status}", 'Сервис вернул ошибку.'],
        };

        return DeliveryResult::permanent($code, $message, $status, latencyMs: $latencyMs);
    }

    public static function timeout(?int $latencyMs = null): DeliveryResult
    {
        return DeliveryResult::transient('timeout', 'Сервис не ответил вовремя. Доставка будет повторена.', latencyMs: $latencyMs);
    }

    public static function network(?int $latencyMs = null): DeliveryResult
    {
        return DeliveryResult::transient('network_error', self::TEMPORARY_MESSAGE, latencyMs: $latencyMs);
    }

    public static function dns(): DeliveryResult
    {
        return DeliveryResult::transient('dns_error', 'Не удалось найти адрес сервиса. Доставка будет повторена.');
    }
}
