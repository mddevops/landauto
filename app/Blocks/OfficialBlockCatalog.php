<?php

namespace App\Blocks;

use App\Enums\BlockCategory;

/**
 * Platform-owned initial official Blocks. A published version is immutable: change a schema
 * only by adding a new version, never by editing an existing entry.
 */
final class OfficialBlockCatalog
{
    public const INITIAL_VERSION = '1.0.0';

    /** Quiz Block (P9-016): visitor answers are resolved against its `steps` state on submission. */
    public const QUIZ_SLUG = 'quiz';

    public const QUIZ_MAX_STEPS = 10;

    /**
     * Category for a newly bootstrapped official Definition; later edits belong to Platform authoring.
     */
    public static function category(string $slug): BlockCategory
    {
        return match (true) {
            $slug === 'header' => BlockCategory::Menu,
            $slug === 'hero' => BlockCategory::Hero,
            $slug === 'benefits' => BlockCategory::Features,
            $slug === 'cta' => BlockCategory::Cta,
            $slug === 'contacts' => BlockCategory::Contacts,
            $slug === 'footer' => BlockCategory::Footer,
            $slug === self::QUIZ_SLUG => BlockCategory::Forms,
            str_starts_with($slug, 'vehicle-') => BlockCategory::Vehicles,
            default => BlockCategory::Other,
        };
    }

    /**
     * Entries are ordered oldest-to-newest per slug; the seeder appends missing versions.
     *
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
            self::block('header', 'Шапка', [
                self::text('logo_text', 'Название', 80, 'Автосалон'),
                ['key' => 'logo', 'type' => 'image', 'label' => 'Логотип'],
                self::repeater('menu', 'Пункты меню', 8, [
                    self::text('label', 'Текст пункта', 40, required: true),
                ]),
                ['key' => 'show_phone', 'type' => 'boolean', 'label' => 'Показывать телефон', 'default' => true],
                [...self::text('phone', 'Телефон', 32), 'visible_if' => ['field' => 'show_phone', 'equals' => true]],
                self::button('button', 'Кнопка', 'Оставить заявку'),
            ], '1.1.0'),
            self::block('header', 'Шапка', [
                self::text('logo_text', 'Название', 80, 'Автосалон'),
                ['key' => 'logo', 'type' => 'image', 'label' => 'Логотип'],
                self::repeater('menu', 'Пункты меню', 8, [
                    self::text('label', 'Текст пункта', 40, required: true),
                    self::action(),
                ]),
                ['key' => 'show_phone', 'type' => 'boolean', 'label' => 'Показывать телефон', 'default' => true],
                [...self::text('phone', 'Телефон', 32), 'visible_if' => ['field' => 'show_phone', 'equals' => true]],
                self::actionButton('button', 'Кнопка', 'Оставить заявку'),
            ], '1.2.0'),
            self::block('hero', 'Первый экран', [
                self::text('eyebrow', 'Надзаголовок', 80),
                self::text('title', 'Заголовок', 120, 'Новые автомобили в наличии', required: true),
                self::textarea('subtitle', 'Подзаголовок', 500),
                ['key' => 'image', 'type' => 'image', 'label' => 'Фоновое изображение'],
                self::select('align', 'Выравнивание', ['left' => 'По левому краю', 'center' => 'По центру'], 'left'),
                self::button('primary_button', 'Основная кнопка', 'Подобрать автомобиль'),
                self::button('secondary_button', 'Дополнительная кнопка'),
            ]),
            self::block('hero', 'Первый экран', [
                self::text('eyebrow', 'Надзаголовок', 80),
                self::text('title', 'Заголовок', 120, 'Новые автомобили в наличии', required: true),
                self::textarea('subtitle', 'Подзаголовок', 500),
                ['key' => 'image', 'type' => 'image', 'label' => 'Фоновое изображение'],
                self::select('align', 'Выравнивание', ['left' => 'По левому краю', 'center' => 'По центру'], 'left'),
                self::actionButton('primary_button', 'Основная кнопка', 'Подобрать автомобиль'),
                self::actionButton('secondary_button', 'Дополнительная кнопка'),
            ], '1.1.0'),
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
            self::block('cta', 'Призыв к действию', [
                self::text('title', 'Заголовок', 120, 'Запишитесь на тест-драйв', required: true),
                self::textarea('text', 'Текст', 500),
                self::actionButton('button', 'Кнопка', 'Записаться'),
                self::select('style', 'Оформление', ['accent' => 'Акцентное', 'muted' => 'Спокойное'], 'accent'),
            ], '1.1.0'),
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
            self::block('footer', 'Подвал', [
                self::text('company_name', 'Название компании', 120),
                self::textarea('text', 'Текст', 500),
                self::repeater('links', 'Ссылки', 12, [
                    self::text('label', 'Текст ссылки', 40, required: true),
                    self::action(),
                ]),
                self::text('copyright', 'Копирайт', 120, '© Все права защищены'),
                self::textarea('legal_notice', 'Юридическая информация', 1000),
            ], '1.1.0'),
            self::block('vehicle-card', 'Карточка автомобиля', [
                self::vehicle(),
                ['key' => 'show_price', 'type' => 'boolean', 'label' => 'Показывать цену', 'default' => true],
                ['key' => 'show_benefit', 'type' => 'boolean', 'label' => 'Показывать выгоду', 'default' => true],
                ['key' => 'show_colors', 'type' => 'boolean', 'label' => 'Показывать цвета', 'default' => true],
                self::actionButton('button', 'Кнопка', 'Подробнее'),
            ]),
            self::block('vehicle-grid', 'Каталог автомобилей', [
                self::text('title', 'Заголовок', 120, 'Автомобили в наличии'),
                self::textarea('subtitle', 'Подзаголовок', 500),
                self::select('source', 'Какие автомобили показывать', ['all' => 'Все автомобили сайта', 'selected' => 'Выбранные'], 'all'),
                [...self::repeater('items', 'Автомобили', 24, [
                    self::vehicle(),
                    self::action(),
                ]), 'visible_if' => ['field' => 'source', 'equals' => 'selected']],
                self::select('columns', 'Колонок', ['two' => '2', 'three' => '3', 'four' => '4'], 'three'),
                ['key' => 'show_price', 'type' => 'boolean', 'label' => 'Показывать цену', 'default' => true],
                ['key' => 'show_benefit', 'type' => 'boolean', 'label' => 'Показывать выгоду', 'default' => true],
                ['key' => 'show_colors', 'type' => 'boolean', 'label' => 'Показывать цвета', 'default' => true],
                [...self::text('button_label', 'Текст кнопки карточки', 40, 'Подробнее'), 'help' => 'Кнопка видна у выбранных автомобилей с настроенным действием.'],
            ]),
            self::block('vehicle-grid', 'Каталог автомобилей', [
                self::text('title', 'Заголовок', 120, 'Автомобили в наличии'),
                self::textarea('subtitle', 'Подзаголовок', 500),
                self::select('source', 'Какие автомобили показывать', ['all' => 'Все автомобили сайта', 'selected' => 'Выбранные'], 'all'),
                [...self::repeater('items', 'Автомобили', 24, [
                    self::vehicle(),
                    self::action(),
                ]), 'visible_if' => ['field' => 'source', 'equals' => 'selected']],
                self::select('columns', 'Колонок', ['two' => '2', 'three' => '3', 'four' => '4'], 'three'),
                self::carousel(),
                ['key' => 'show_price', 'type' => 'boolean', 'label' => 'Показывать цену', 'default' => true],
                ['key' => 'show_benefit', 'type' => 'boolean', 'label' => 'Показывать выгоду', 'default' => true],
                ['key' => 'show_colors', 'type' => 'boolean', 'label' => 'Показывать цвета', 'default' => true],
                [...self::text('button_label', 'Текст кнопки карточки', 40, 'Подробнее'), 'help' => 'Кнопка видна у выбранных автомобилей с настроенным действием.'],
            ], '1.1.0'),
            self::block('vehicle-gallery', 'Галерея автомобиля', [
                self::vehicle(),
                ['key' => 'show_title', 'type' => 'boolean', 'label' => 'Показывать название и цену', 'default' => true],
                ['key' => 'show_colors', 'type' => 'boolean', 'label' => 'Показывать выбор цвета', 'default' => true],
            ]),
            self::block('vehicle-offers', 'Цены и предложения', [
                self::vehicle(),
                self::text('title', 'Заголовок', 120, 'Цены и комплектации'),
                self::actionButton('button', 'Кнопка предложения', 'Оставить заявку'),
            ]),
            self::block('vehicle-characteristics', 'Характеристики автомобиля', [
                self::vehicle(),
                self::text('title', 'Заголовок', 120, 'Характеристики'),
            ]),
            self::block('vehicle-equipment', 'Оснащение автомобиля', [
                self::vehicle(),
                self::text('title', 'Заголовок', 120, 'Оснащение'),
                ['key' => 'show_optional', 'type' => 'boolean', 'label' => 'Показывать опции за доплату', 'default' => true],
            ]),
            self::block(self::QUIZ_SLUG, 'Квиз', [
                self::text('title', 'Заголовок', 120, 'Подберём автомобиль за минуту', required: true),
                self::textarea('subtitle', 'Подзаголовок', 500),
                self::repeater('steps', 'Вопросы', self::QUIZ_MAX_STEPS, [
                    self::text('question', 'Вопрос', 160, required: true),
                    self::repeater('options', 'Варианты ответа', 8, [
                        self::text('label', 'Текст варианта', 80, required: true),
                    ]),
                ]),
                self::text('result_title', 'Заголовок результата', 120, 'Подборка готова'),
                self::textarea('result_text', 'Текст результата', 500),
                self::actionButton('button', 'Кнопка заявки', 'Получить подборку'),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function vehicle(): array
    {
        return ['key' => 'vehicle', 'type' => 'vehicle', 'label' => 'Автомобиль', 'required' => true];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array{slug: string, name: string, version: string, schema: array{fields: list<array<string, mixed>>}}
     */
    private static function block(string $slug, string $name, array $fields, string $version = self::INITIAL_VERSION): array
    {
        return ['slug' => $slug, 'name' => $name, 'version' => $version, 'schema' => ['fields' => $fields]];
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
     * @return array<string, mixed>
     */
    private static function actionButton(string $key, string $label, ?string $default = null): array
    {
        return ['key' => $key, 'type' => 'group', 'label' => $label, 'fields' => [
            self::text('label', 'Текст кнопки', 40, $default),
            self::action(),
        ]];
    }

    /**
     * Reusable vendor-neutral Carousel capability (D-032); the frontend maps these values to
     * one implementation. Bounded choices keep autoplay delay and layout within safe limits.
     *
     * @return array<string, mixed>
     */
    private static function carousel(): array
    {
        $whenEnabled = ['visible_if' => ['field' => 'enabled', 'equals' => true]];

        return ['key' => 'carousel', 'type' => 'group', 'label' => 'Карусель', 'fields' => [
            ['key' => 'enabled', 'type' => 'boolean', 'label' => 'Показывать каруселью', 'default' => false],
            [...self::select('per_view', 'Карточек на экране', ['one' => '1', 'two' => '2', 'three' => '3', 'four' => '4'], 'three'), ...$whenEnabled, 'help' => 'На телефоне всегда одна карточка, на планшете — не больше двух.'],
            [...self::select('gap', 'Отступ между карточками', ['small' => 'Маленький', 'medium' => 'Средний', 'large' => 'Большой'], 'medium'), ...$whenEnabled],
            ['key' => 'arrows', 'type' => 'boolean', 'label' => 'Показывать стрелки', 'default' => true, ...$whenEnabled],
            ['key' => 'dots', 'type' => 'boolean', 'label' => 'Показывать точки', 'default' => true, ...$whenEnabled],
            ['key' => 'loop', 'type' => 'boolean', 'label' => 'Зацикливать прокрутку', 'default' => false, ...$whenEnabled],
            ['key' => 'autoplay', 'type' => 'boolean', 'label' => 'Автопрокрутка', 'default' => false, ...$whenEnabled, 'help' => 'Останавливается при наведении и фокусе; выключена, если посетитель просит уменьшить анимацию.'],
            [...self::select('delay', 'Интервал автопрокрутки', ['s3' => '3 секунды', 's5' => '5 секунд', 's8' => '8 секунд'], 's5'), 'visible_if' => ['field' => 'autoplay', 'equals' => true]],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function action(): array
    {
        return ['key' => 'action', 'type' => 'action', 'label' => 'Действие'];
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
