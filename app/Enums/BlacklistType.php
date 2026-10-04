<?php

namespace App\Enums;

enum BlacklistType: string
{
    case Ip = 'ip';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Ip => 'IP-адрес',
            self::Phone => 'Телефон',
        };
    }
}
