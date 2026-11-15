<?php

namespace App\Enums;

/**
 * Generated template Page of a vehicle section (ADR-011 §3).
 * Catalog (D-131): index / mark / item. Used / Specials (X-038): index / mark / model / item.
 */
enum SectionPageRole: string
{
    case Index = 'index';
    case Mark = 'mark';
    case Model = 'model';
    case Item = 'item';

    public function label(): string
    {
        return match ($this) {
            self::Index => 'Все автомобили',
            self::Mark => 'Страница марки',
            self::Model => 'Страница модели',
            self::Item => 'Карточка автомобиля',
        };
    }

    /**
     * Path segments after the prefix, shown as an example address.
     *
     * @return list<string>
     */
    public function exampleSegments(SiteSectionType $section = SiteSectionType::Catalog): array
    {
        if ($section->atRoot()) {
            return $this === self::Item ? ['rio-iv-restyling'] : [];
        }

        if ($section->fromStock()) {
            return match ($this) {
                self::Index => [],
                self::Mark => ['kia'],
                self::Model => ['kia', 'rio'],
                self::Item => ['kia', 'rio', '4564'],
            };
        }

        return match ($this) {
            self::Index => [],
            self::Mark => ['kia'],
            self::Model => ['kia'],
            self::Item => ['kia', 'rio-iv-restyling'],
        };
    }

    /**
     * Template Pages that show one concrete vehicle context, picked as a sample in the Designer.
     */
    public function needsSample(): bool
    {
        return $this !== self::Index;
    }
}
