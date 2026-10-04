<?php

namespace App\Enums;

enum PopupSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Компактный',
            self::Medium => 'Средний',
            self::Large => 'Широкий',
        };
    }
}
