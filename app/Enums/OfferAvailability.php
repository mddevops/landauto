<?php

namespace App\Enums;

enum OfferAvailability: string
{
    case InStock = 'in_stock';
    case InTransit = 'in_transit';
    case OnOrder = 'on_order';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'В наличии',
            self::InTransit => 'В пути',
            self::OnOrder => 'Под заказ',
        };
    }
}
