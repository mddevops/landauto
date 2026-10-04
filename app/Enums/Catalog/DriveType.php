<?php

namespace App\Enums\Catalog;

/**
 * Recommended drive codes (Catalog V2, auto_modifications.drive).
 */
enum DriveType: string
{
    case Fwd = 'fwd';
    case Rwd = 'rwd';
    case Awd = 'awd';

    public function label(): string
    {
        return match ($this) {
            self::Fwd => 'Передний',
            self::Rwd => 'Задний',
            self::Awd => 'Полный',
        };
    }
}
