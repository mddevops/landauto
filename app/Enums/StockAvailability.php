<?php

namespace App\Enums;

/**
 * Dealer availability of a Stock vehicle (ADR-012).
 */
enum StockAvailability: string
{
    case InStock = 'in_stock';
    case OnOrder = 'on_order';
    case InTransit = 'in_transit';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'В наличии',
            self::OnOrder => 'На заказ',
            self::InTransit => 'В пути',
        };
    }
}
