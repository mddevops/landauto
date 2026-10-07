<?php

namespace App\Enums;

/**
 * Developer Profile lifecycle (D-093). There is no pending state: a Super Admin grants an active
 * profile explicitly and may suspend or reactivate it.
 */
enum DeveloperProfileStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Активен',
            self::Suspended => 'Приостановлен',
        };
    }
}
