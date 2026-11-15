<?php

namespace App\Enums;

/**
 * PTS document type for used Stock vehicles (ADR-012). Optional in MVP.
 */
enum StockPtsDocumentType: string
{
    case OriginalOrElectronic = 'original_or_electronic';
    case Duplicate = 'duplicate';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::OriginalOrElectronic => 'Оригинал / Электронный ПТС',
            self::Duplicate => 'Дубликат',
            self::None => 'Нет ПТС',
        };
    }
}
