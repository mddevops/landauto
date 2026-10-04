<?php

namespace App\Enums;

enum PopupAnimation: string
{
    case None = 'none';
    case Fade = 'fade';
    case SlideUp = 'slide_up';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Без анимации',
            self::Fade => 'Плавное появление',
            self::SlideUp => 'Выезд снизу',
        };
    }
}
