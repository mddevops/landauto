<?php

namespace App\Enums;

/**
 * Physical / commercial condition of a Stock vehicle (ADR-012).
 */
enum StockCondition: string
{
    case New = 'new';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Used => 'С пробегом',
        };
    }
}
