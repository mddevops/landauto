<?php

namespace App\Enums;

/**
 * Platform capabilities. Workspace permissions never grant any of these.
 */
enum PlatformPermission: string
{
    case ViewCatalog = 'view_catalog';
    case EditCatalog = 'edit_catalog';
    case ManageCatalogMedia = 'manage_catalog_media';
    case ManageDevelopers = 'manage_developers';
}
