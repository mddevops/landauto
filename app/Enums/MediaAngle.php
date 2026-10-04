<?php

namespace App\Enums;

/**
 * Prepared vehicle picture angles; case order is the display order.
 */
enum MediaAngle: string
{
    case Front = 'front';
    case FrontThreeQuarter = 'front_3_4';
    case Side = 'side';
    case RearThreeQuarter = 'rear_3_4';
    case Rear = 'rear';
    case Interior = 'interior';

    public function label(): string
    {
        return match ($this) {
            self::Front => 'Спереди',
            self::FrontThreeQuarter => 'Спереди 3/4',
            self::Side => 'Сбоку',
            self::RearThreeQuarter => 'Сзади 3/4',
            self::Rear => 'Сзади',
            self::Interior => 'Салон',
        };
    }

    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
