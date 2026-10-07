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
    // Official platform-owned Blocks / Templates (D-117, D-118); needs no Developer Profile.
    case ManagePlatformContent = 'manage_platform_content';
    // Grant / revoke Site licenses for catalog items (D-079); Super Admin only.
    case ManageSiteLicenses = 'manage_site_licenses';
}
