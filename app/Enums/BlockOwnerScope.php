<?php

namespace App\Enums;

/**
 * Explicit Block Definition ownership (D-117). The creator / editor User is audit identity only.
 */
enum BlockOwnerScope: string
{
    case Platform = 'platform';
    case Developer = 'developer';
    case WorkspacePrivate = 'workspace_private';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Платформа Landflow',
            self::Developer => 'Разработчик',
            self::WorkspacePrivate => 'Приватный блок пространства',
        };
    }
}
