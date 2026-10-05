<?php

namespace App\Integrations\Mapping;

/**
 * Central registry of mapping sources (FORMS_AND_INTEGRATIONS.md §22). A source is a stable
 * semantic key, never an expression or path: a fixed key below, `field.<form field key>`,
 * `override.<binding override key>` or `constant` with a static value.
 */
final class MappingSources
{
    public const CONSTANT = 'constant';

    public const FIELD_PREFIX = 'field.';

    public const OVERRIDE_PREFIX = 'override.';

    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    /** Fixed sources: key → [group, Russian label]. */
    public const FIXED = [
        'submission.public_id' => ['Заявка', 'ID заявки'],
        'submission.submitted_at' => ['Заявка', 'Дата и время заявки'],
        'form.public_id' => ['Заявка', 'ID формы'],
        'form.name' => ['Заявка', 'Название формы'],
        'page.title' => ['Заявка', 'Страница'],
        'page.url' => ['Заявка', 'Адрес страницы'],
        'referrer' => ['Заявка', 'Источник перехода'],
        'popup.name' => ['Заявка', 'Попап'],
        'block.name' => ['Заявка', 'Блок'],
        'utm.source' => ['Метки UTM', 'utm_source'],
        'utm.medium' => ['Метки UTM', 'utm_medium'],
        'utm.campaign' => ['Метки UTM', 'utm_campaign'],
        'utm.content' => ['Метки UTM', 'utm_content'],
        'utm.term' => ['Метки UTM', 'utm_term'],
        'vehicle.public_id' => ['Автомобиль', 'ID автомобиля'],
        'vehicle.title' => ['Автомобиль', 'Название автомобиля'],
        'vehicle.mark' => ['Автомобиль', 'Марка'],
        'vehicle.model' => ['Автомобиль', 'Модель'],
        'vehicle.generation' => ['Автомобиль', 'Поколение'],
        'vehicle.series' => ['Автомобиль', 'Кузов'],
        'vehicle.color' => ['Автомобиль', 'Цвет'],
        'offer.public_id' => ['Предложение', 'ID предложения'],
        'offer.modification' => ['Предложение', 'Модификация'],
        'offer.equipment' => ['Предложение', 'Комплектация'],
        'offer.price' => ['Предложение', 'Цена (число)'],
        'offer.price_minor' => ['Предложение', 'Цена в копейках'],
        'offer.currency' => ['Предложение', 'Валюта'],
        'offer.price_label' => ['Предложение', 'Цена (текст)'],
        'site.public_id' => ['Сайт', 'ID сайта'],
        'site.name' => ['Сайт', 'Название сайта'],
        'site.url' => ['Сайт', 'Адрес сайта'],
    ];

    public static function isFixed(string $source): bool
    {
        return array_key_exists($source, self::FIXED);
    }

    public static function fieldKey(string $source): ?string
    {
        return self::suffix($source, self::FIELD_PREFIX);
    }

    public static function overrideKey(string $source): ?string
    {
        return self::suffix($source, self::OVERRIDE_PREFIX);
    }

    /**
     * Value of a source for one Submission; null when the Submission has no such value.
     */
    public static function resolve(string $source, MappingContext $context, ?string $constant = null): string|int|float|bool|null
    {
        if ($source === self::CONSTANT) {
            return $constant;
        }

        if (($key = self::fieldKey($source)) !== null) {
            return $context->fields[$key] ?? null;
        }

        if (($key = self::overrideKey($source)) !== null) {
            return $context->overrides[$key] ?? null;
        }

        $trusted = $context->trusted;
        $minor = $trusted['offer']['price_minor'] ?? null;

        $value = match ($source) {
            'submission.public_id' => $context->submissionPublicId,
            'submission.submitted_at' => $context->submittedAt,
            'form.public_id' => $context->formPublicId,
            'form.name' => $context->formName,
            'page.title' => $trusted['page']['title'] ?? null,
            'page.url' => $context->visitor['page_url'] ?? null,
            'referrer' => $context->visitor['referrer'] ?? null,
            'popup.name' => $trusted['popup']['name'] ?? null,
            'block.name' => $trusted['block']['name'] ?? null,
            'utm.source' => $context->visitor['utm_source'] ?? null,
            'utm.medium' => $context->visitor['utm_medium'] ?? null,
            'utm.campaign' => $context->visitor['utm_campaign'] ?? null,
            'utm.content' => $context->visitor['utm_content'] ?? null,
            'utm.term' => $context->visitor['utm_term'] ?? null,
            'vehicle.public_id' => $trusted['vehicle']['public_id'] ?? null,
            'vehicle.title' => $trusted['vehicle']['title'] ?? null,
            'vehicle.mark' => $trusted['vehicle']['mark'] ?? null,
            'vehicle.model' => $trusted['vehicle']['model'] ?? null,
            'vehicle.generation' => $trusted['vehicle']['generation'] ?? null,
            'vehicle.series' => $trusted['vehicle']['series'] ?? null,
            'vehicle.color' => $trusted['media_set']['name'] ?? null,
            'offer.public_id' => $trusted['offer']['public_id'] ?? null,
            'offer.modification' => $trusted['offer']['modification'] ?? null,
            'offer.equipment' => $trusted['offer']['equipment'] ?? null,
            'offer.price' => is_int($minor) ? $minor / 100 : null,
            'offer.price_minor' => is_int($minor) ? $minor : null,
            'offer.currency' => $trusted['offer']['currency'] ?? null,
            'offer.price_label' => $trusted['offer']['price_label'] ?? null,
            'site.public_id' => $context->sitePublicId,
            'site.name' => $context->siteName,
            'site.url' => $context->siteUrl === '' ? null : $context->siteUrl,
            default => throw new MappingFailure('unknown_source', 'Неизвестный источник данных в настройке передачи.'),
        };

        return is_scalar($value) ? $value : null;
    }

    private static function suffix(string $source, string $prefix): ?string
    {
        if (! str_starts_with($source, $prefix)) {
            return null;
        }

        $key = substr($source, strlen($prefix));

        return preg_match(self::KEY_PATTERN, $key) === 1 ? $key : null;
    }
}
