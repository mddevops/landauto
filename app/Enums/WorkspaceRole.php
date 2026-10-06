<?php

namespace App\Enums;

/**
 * Workspace system roles (PERMISSIONS.md §5). Their permission catalog is defined in P1-008;
 * business logic should check permissions, not role names, except for owner semantics.
 */
enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Designer = 'designer';
    case ContentEditor = 'content_editor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Владелец',
            self::Admin => 'Администратор',
            self::Designer => 'Дизайнер',
            self::ContentEditor => 'Редактор контента',
        };
    }
}
