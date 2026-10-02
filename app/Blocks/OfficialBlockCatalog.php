<?php

namespace App\Blocks;

/**
 * Platform-owned initial official Blocks. A published version is immutable: change a schema
 * only by adding a new version, never by editing an existing entry.
 */
final class OfficialBlockCatalog
{
    public const INITIAL_VERSION = '1.0.0';

    /**
     * @return list<array{slug: string, name: string, version: string, schema: array{fields: list<array<string, mixed>>}}>
     */
    public static function blocks(): array
    {
        return [
            self::block('header', 'Шапка', [
                self::text('logo_text', 'Название', 80, 'Автосалон'),
                ['key' => 'logo', 'type' => 'image', 'label' => 'Логотип'],
                self::repeater('menu', 'Пункты меню', 8, [
                    self::text('label', 'Текст пункта', 40, required: true),
                ]),
                self::text('phone', 'Телефон', 32),
                ['key' => 'show_phone', 'type' => 'boolean', 'label' => 'Показывать телефон', 'default' => true],
                self::button('button', 'Кнопка', 'Оставить заявку'),
            ]),
            self::block('hero', 'Первый экран', [
                self::text('eyebrow', 'Надзаголовок', 80),
                self::text('title', 'Заголовок', 120, 'Новые автомобили в наличии', required: true),
                self::textarea('subtitle', 'Подзаголовок', 500),
                ['key' => 'image', 'type' => 'image', 'label' => 'Фоновое изображение'],
                self::select('align', 'Выравнивание', ['left' => 'По левому краю', 'center' => 'По центру'], 'left'),
                self::button('primary_button', 'Основная кнопка', 'Подобрать автомобиль'),
                self::button('secondary_button', 'Дополнительная кнопка'),
            ]),
            self::block('benefits', 'Преимущества', [
                self::text('title', 'Заголовок', 120, 'Почему выбирают нас'),
                self::textarea('subtitle', 'Подзаголовок', 500),
                self::select('columns', 'Колонок', ['two' => '2', 'three' => '3', 'four' => '4'], 'three'),
                self::repeater('items', 'Преимущества', 12, [
                    self::text('title', 'Заголовок', 80, required: true),
                    self::textarea('text', 'Описание', 300),
                ], minItems: 1),
            ]),
            self::block('cta', 'Призыв к действию', [
                self::text('title', 'Заголовок', 120, 'Запишитесь на тест-драйв', required: true),
                self::textarea('text', 'Текст', 500),
                self::button('button', 'Кнопка', 'Записаться'),
                self::select('style', 'Оформление', ['accent' => 'Акцентное', 'muted' => 'Спокойное'], 'accent'),
            ]),
            self::block('contacts', 'Контакты', [
                self::text('title', 'Заголовок', 120, 'Контакты'),
                self::textarea('address', 'Адрес', 300),
                self::text('phone', 'Телефон', 32),
                self::text('email', 'Email', 120),
                self::textarea('working_hours', 'Режим работы', 300),
            ]),
            self::block('footer', 'Подвал', [
                self::text('company_name', 'Название компании', 120),
                self::textarea('text', 'Текст', 500),
                self::repeater('links', 'Ссылки', 12, [
                    self::text('label', 'Текст ссылки', 40, required: true),
                ]),
                self::text('copyright', 'Копирайт', 120, '© Все права защищены'),
                self::textarea('legal_notice', 'Юридическая информация', 1000),
            ]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{slug: string, name: string, version: string, schema: array{fields: list<array<string, mixed>>}}
     */
    private static function block(string $slug, string $name, array $fields): array
    {
        return ['slug' => $slug, 'name' => $name, 'version' => self::INITIAL_VERSION, 'schema' => ['fields' => $fields]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(string $key, string $label, int $maxLength, ?string $default = null, bool $required = false): array
    {
        return array_filter([
            'key' => $key,
            'type' => 'text',
            'label' => $label,
            'required' => $required ?: null,
            'max_length' => $maxLength,
            'default' => $default,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private static function textarea(string $key, string $label, int $maxLength): array
    {
        return ['key' => $key, 'type' => 'textarea', 'label' => $label, 'max_length' => $maxLength];
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, mixed>
     */
    private static function select(string $key, string $label, array $options, string $default): array
    {
        return [
            'key' => $key,
            'type' => 'select',
            'label' => $label,
            'options' => array_map(
                fn (string $value, string $optionLabel): array => ['value' => $value, 'label' => $optionLabel],
                array_keys($options),
                array_values($options),
            ),
            'default' => $default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function button(string $key, string $label, ?string $default = null): array
    {
        return ['key' => $key, 'type' => 'group', 'label' => $label, 'fields' => [
            self::text('label', 'Текст кнопки', 40, $default),
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private static function repeater(string $key, string $label, int $maxItems, array $fields, int $minItems = 0): array
    {
        return array_filter([
            'key' => $key,
            'type' => 'repeater',
            'label' => $label,
            'fields' => $fields,
            'min_items' => $minItems ?: null,
            'max_items' => $maxItems,
        ], fn (mixed $value): bool => $value !== null);
    }
}
