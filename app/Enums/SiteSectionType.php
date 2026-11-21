<?php

namespace App\Enums;

/**
 * Vehicle section of a Site (ADR-011). Multi-page Sites hold Catalog (Used and Specials are fed by
 * Stock, X-037 / X-038); a Landing holds only the vehicle card, served at the Site root (§7).
 */
enum SiteSectionType: string
{
    case Catalog = 'catalog';
    case Used = 'used';
    case Specials = 'specials';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => 'Каталог автомобилей',
            self::Used => 'Автомобили с пробегом',
            self::Specials => 'Новые',
            self::Card => 'Карточка автомобиля',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Catalog => 'Новые автомобили сайта: страницы марок и карточки автомобилей.',
            self::Used => 'Автомобили с пробегом со склада.',
            self::Specials => 'Новые автомобили со склада.',
            self::Card => 'Отдельная страница каждого автомобиля лендинга: цвета, предложения, характеристики и комплектации.',
        };
    }

    public function defaultPrefix(): string
    {
        return $this === self::Card ? '' : $this->value;
    }

    /**
     * Card addresses sit at the Site root without a prefix or a mark: `/rio-iv-restyling`.
     */
    public function atRoot(): bool
    {
        return $this === self::Card;
    }

    /**
     * Sections a Site of this type can show in «Разделах».
     *
     * @return list<self>
     */
    public static function offeredFor(SiteType $siteType): array
    {
        return match ($siteType) {
            SiteType::MultiPage => [self::Catalog, self::Used, self::Specials],
            SiteType::Landing => [self::Card],
            default => [],
        };
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Sections whose vehicles come from Workspace Stock (ADR-012 / X-038).
     *
     * @return list<self>
     */
    public static function stockSections(): array
    {
        return [self::Used, self::Specials];
    }

    public function fromStock(): bool
    {
        return in_array($this, self::stockSections(), true);
    }

    /**
     * Template Pages the section generates.
     *
     * @return list<SectionPageRole>
     */
    public function roles(): array
    {
        return match ($this) {
            self::Card => [SectionPageRole::Item],
            self::Used, self::Specials => [
                SectionPageRole::Index,
                SectionPageRole::Mark,
                SectionPageRole::Model,
                SectionPageRole::Item,
            ],
            self::Catalog => [
                SectionPageRole::Index,
                SectionPageRole::Mark,
                SectionPageRole::Item,
            ],
        };
    }

    /**
     * Sections whose vehicles come from the Site vehicles (not from Stock).
     *
     * @return list<self>
     */
    public static function vehicleSections(): array
    {
        return [self::Catalog, self::Card];
    }
}
