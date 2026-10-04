<?php

namespace App\Enums;

/**
 * Safe navigation actions of an `action` Block field. Popup/Form actions arrive with Phase 4.
 */
enum BlockActionType: string
{
    case OpenUrl = 'open_url';
    case OpenPage = 'open_page';
    case ScrollTo = 'scroll_to';
    case Phone = 'phone';
    case Email = 'email';

    /** State key holding the action target next to `type`. */
    public function targetKey(): string
    {
        return match ($this) {
            self::OpenUrl => 'url',
            self::OpenPage => 'page',
            self::ScrollTo => 'block',
            self::Phone => 'phone',
            self::Email => 'email',
        };
    }
}
