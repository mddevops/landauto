<?php

namespace App\Enums;

enum PublishedVersionStatus: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Building => 'Собирается',
            self::Ready => 'Готова',
            self::Failed => 'Ошибка',
        };
    }
}
