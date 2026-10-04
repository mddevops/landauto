<?php

namespace App\Enums\Catalog;

/**
 * Editing/display view of auto_option_values: Unknown is the absence of a row, never stored.
 */
enum OptionAvailability: string
{
    case Unknown = 'unknown';
    case Standard = 'standard';
    case Optional = 'optional';

    public static function fromIsBase(?bool $isBase): self
    {
        return match ($isBase) {
            null => self::Unknown,
            true => self::Standard,
            false => self::Optional,
        };
    }

    public function isBase(): ?bool
    {
        return match ($this) {
            self::Unknown => null,
            self::Standard => true,
            self::Optional => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Нет сведений',
            self::Standard => 'В базовой комплектации',
            self::Optional => 'За доплату',
        };
    }
}
