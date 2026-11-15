<?php

namespace App\Enums;

/**
 * Where a Component appears on every composed Page (ADR-013 / D-130).
 */
enum ComponentPosition: string
{
    case First = 'first';
    case Last = 'last';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::First => 'В начале',
            self::Last => 'В конце',
            self::Free => 'Свободно',
        };
    }
}
