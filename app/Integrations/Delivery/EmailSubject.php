<?php

namespace App\Integrations\Delivery;

/**
 * Email subject template with a fixed placeholder allowlist; anything else in braces is rejected
 * at save time. Rendered values are single-line and length-capped, so a subject can never inject
 * mail headers.
 */
final class EmailSubject
{
    public const DEFAULT = 'Новая заявка: {form.name}';

    public const MAX_LENGTH = 150;

    /** Placeholder → mapping source key. */
    public const PLACEHOLDERS = [
        'form.name' => 'form.name',
        'site.name' => 'site.name',
        'vehicle.title' => 'vehicle.title',
        'offer.price' => 'offer.price_label',
    ];

    /**
     * @return list<string> Unknown placeholders in the template.
     */
    public static function unknownPlaceholders(string $template): array
    {
        preg_match_all('/\{([^}]*)\}/', $template, $matches);

        return array_values(array_diff($matches[1], array_keys(self::PLACEHOLDERS)));
    }

    /**
     * @param  array<string, string|null>  $values  Mapping source key → value.
     */
    public static function render(?string $template, array $values): string
    {
        $subject = preg_replace_callback('/\{([^}]*)\}/', function (array $match) use ($values): string {
            $source = self::PLACEHOLDERS[$match[1]] ?? null;

            return $source === null ? '' : (string) ($values[$source] ?? '');
        }, $template === null || $template === '' ? self::DEFAULT : $template) ?? self::DEFAULT;

        $subject = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $subject));

        return mb_substr($subject === '' ? 'Новая заявка' : $subject, 0, self::MAX_LENGTH);
    }
}
