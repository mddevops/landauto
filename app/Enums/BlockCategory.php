<?php

namespace App\Enums;

/**
 * Creator Studio / catalog grouping of Block Definitions; editable metadata, not runtime behavior.
 */
enum BlockCategory: string
{
    case Menu = 'menu';
    case Hero = 'hero';
    case Features = 'features';
    case Content = 'content';
    case Vehicles = 'vehicles';
    case Gallery = 'gallery';
    case Forms = 'forms';
    case Cta = 'cta';
    case Contacts = 'contacts';
    case Footer = 'footer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Menu => 'Меню',
            self::Hero => 'Первый экран',
            self::Features => 'Преимущества',
            self::Content => 'Контент',
            self::Vehicles => 'Автомобили',
            self::Gallery => 'Галерея',
            self::Forms => 'Формы',
            self::Cta => 'Призыв к действию',
            self::Contacts => 'Контакты',
            self::Footer => 'Подвал',
            self::Other => 'Другое',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}
