<?php

namespace App\Enums;

/**
 * Generated template Page of a vehicle section (ADR-011 §3, D-131): one Page renders every mark
 * or vehicle of the section.
 */
enum SectionPageRole: string
{
    case Index = 'index';
    case Mark = 'mark';
    case Item = 'item';

    public function label(): string
    {
        return match ($this) {
            self::Index => 'Все автомобили',
            self::Mark => 'Страница марки',
            self::Item => 'Карточка автомобиля',
        };
    }

    /**
     * Path segments after the prefix, shown as an example address.
     *
     * @return list<string>
     */
    public function exampleSegments(): array
    {
        return match ($this) {
            self::Index => [],
            self::Mark => ['kia'],
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
