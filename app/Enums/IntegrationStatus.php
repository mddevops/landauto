<?php

namespace App\Enums;

/**
 * Lifecycle of Integration Profiles and Site bindings. Archived records stay only as history for
 * existing routes and deliveries; they cannot be selected or delivered to.
 */
enum IntegrationStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активна',
            self::Disabled => 'Выключена',
            self::Archived => 'В архиве',
        };
    }
}
