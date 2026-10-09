<?php

namespace App\Enums;

/**
 * How a Block Version renders: `official` by the trusted application registry, `sandboxed` from
 * its immutable authored sources inside the opaque-origin sandbox only (ADR-008), `native` from
 * its immutable authored sources compiled at Publish into host DOM on the published Site (ADR-009).
 */
enum BlockRuntime: string
{
    case Official = 'official';
    case Sandboxed = 'sandboxed';
    case Native = 'native';

    public function label(): string
    {
        return match ($this) {
            self::Official => 'Встроенный',
            self::Sandboxed => 'Код студии',
            self::Native => 'Нативный',
        };
    }

    public function hasAuthoredSource(): bool
    {
        return $this !== self::Official;
    }
}
