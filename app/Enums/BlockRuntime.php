<?php

namespace App\Enums;

/**
 * How a Block Version renders (ADR-008 §8): `official` by the trusted application registry,
 * `sandboxed` from its immutable authored sources inside the opaque-origin sandbox only.
 */
enum BlockRuntime: string
{
    case Official = 'official';
    case Sandboxed = 'sandboxed';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'Встроенный',
            self::Sandboxed => 'Код студии',
        };
    }
}
