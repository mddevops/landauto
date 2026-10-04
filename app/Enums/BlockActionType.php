<?php

namespace App\Enums;

/**
 * Platform-defined actions of an `action` Block field (D-034). Targets are data, never script.
 */
enum BlockActionType: string
{
    case OpenUrl = 'open_url';
    case OpenPage = 'open_page';
    case ScrollTo = 'scroll_to';
    case Phone = 'phone';
    case Email = 'email';
    case OpenPopup = 'open_popup';

    /** State key holding the action target next to `type`. */
    public function targetKey(): string
    {
        return match ($this) {
            self::OpenUrl => 'url',
            self::OpenPage => 'page',
            self::ScrollTo => 'block',
            self::Phone => 'phone',
            self::Email => 'email',
            self::OpenPopup => 'popup',
        };
    }
}
