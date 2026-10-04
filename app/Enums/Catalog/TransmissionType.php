<?php

namespace App\Enums\Catalog;

/**
 * Recommended transmission codes (Catalog V2, auto_modifications.transmission).
 */
enum TransmissionType: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
    case Cvt = 'cvt';
    case Robot = 'robot';
    case Reducer = 'reducer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Механика',
            self::Automatic => 'Автомат',
            self::Cvt => 'Вариатор',
            self::Robot => 'Робот',
            self::Reducer => 'Редуктор',
            self::Other => 'Другое',
        };
    }
}
