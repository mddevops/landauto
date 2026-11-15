<?php

namespace App\Enums;

/**
 * Editorial status of a Stock vehicle (ADR-012). Only Ready items may enter Used / Specials.
 */
enum StockVehicleStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Ready => 'Готов',
            self::Archived => 'В архиве',
        };
    }
}
