<?php

namespace App\Enums;

/**
 * Vehicle section of a multi-page Site (ADR-011). Used and Specials are fed by Stock (X-037 / X-038).
 */
enum SiteSectionType: string
{
    case Catalog = 'catalog';
    case Used = 'used';
    case Specials = 'specials';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => 'Каталог автомобилей',
            self::Used => 'Автомобили с пробегом',
            self::Specials => 'Спецпредложения',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Catalog => 'Новые автомобили сайта: страницы марок, моделей и карточки автомобилей.',
            self::Used => 'Автомобили с пробегом со склада.',
            self::Specials => 'Новые автомобили и спецпредложения со склада.',
        };
    }

    public function defaultPrefix(): string
    {
        return $this->value;
    }

    /**
     * Used and Specials need the Stock section, which does not exist yet.
     */
    public function isAvailable(): bool
    {
        return $this === self::Catalog;
    }
}
