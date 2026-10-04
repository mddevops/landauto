<?php

namespace App\Enums\Catalog;

/**
 * Recommended normalized power unit codes (Catalog V2, auto_modifications.engine).
 */
enum EngineType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case HybridPetrol = 'hybrid_petrol';
    case HybridDiesel = 'hybrid_diesel';
    case Electric = 'electric';
    case Gas = 'gas';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Petrol => 'Бензин',
            self::Diesel => 'Дизель',
            self::HybridPetrol => 'Гибрид (бензин)',
            self::HybridDiesel => 'Гибрид (дизель)',
            self::Electric => 'Электро',
            self::Gas => 'Газ',
            self::Other => 'Другое',
        };
    }
}
