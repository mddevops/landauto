<?php

namespace App\Enums;

/**
 * Minimal listing lifecycle (P10-001). There is no submitted / review / approved state: the author
 * publishes after deterministic checks (D-120).
 */
enum MarketplaceListingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Published => 'Опубликовано',
        };
    }
}
