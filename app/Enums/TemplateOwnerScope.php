<?php

namespace App\Enums;

/**
 * Template ownership (P9-007, like D-117 for Blocks): official Landflow Templates or one Developer
 * Profile. Workspace-private Templates do not exist.
 */
enum TemplateOwnerScope: string
{
    case Platform = 'platform';
    case Developer = 'developer';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Landflow',
            self::Developer => 'Разработчик',
        };
    }
}
