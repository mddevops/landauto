<?php

namespace App\Enums;

/**
 * Amount-based Site Offer benefits (ADR-004 minor units in the offer currency).
 */
enum BenefitType: string
{
    case Discount = 'discount';
    case TradeIn = 'trade_in';
    case Credit = 'credit';
    case Leasing = 'leasing';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Скидка',
            self::TradeIn => 'Выгода по трейд-ин',
            self::Credit => 'Выгода в кредит',
            self::Leasing => 'Выгода в лизинг',
        };
    }
}
