<?php

namespace App\Enums;

/**
 * Vehicle photo angles of a colour (ADR-010); case order is the display order, left to right
 * around the car. Rear and top views are not used.
 */
enum MediaAngle: string
{
    case SideLeft = 'side_left';
    case FrontThreeQuarterLeft = 'front_3_4_left';
    case Front = 'front';
    case FrontThreeQuarterRight = 'front_3_4_right';
    case SideRight = 'side_right';

    /**
     * Preferred angle for cards when the customer has not chosen one.
     */
    public const PREFERRED = self::FrontThreeQuarterLeft;

    public function label(): string
    {
        return match ($this) {
            self::SideLeft => 'Сбоку, смотрит влево',
            self::FrontThreeQuarterLeft => '3/4, смотрит влево',
            self::Front => 'Спереди',
            self::FrontThreeQuarterRight => '3/4, смотрит вправо',
            self::SideRight => 'Сбоку, смотрит вправо',
        };
    }

    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $angle): array => ['value' => $angle->value, 'label' => $angle->label()], self::cases());
    }
}
