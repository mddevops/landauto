<?php

namespace App\Enums;

/**
 * Landflow internal platform roles; never derived from Workspace membership, user ID or email.
 */
enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';
    case CatalogManager = 'catalog_manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Суперадминистратор',
            self::CatalogManager => 'Менеджер каталога',
        };
    }
}
