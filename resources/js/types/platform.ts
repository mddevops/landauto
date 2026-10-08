export type PlatformPermission =
    | 'view_catalog'
    | 'edit_catalog'
    | 'manage_catalog_media'
    | 'manage_developers'
    | 'manage_platform_content'
    | 'manage_catalog_licenses';

export type PlatformContext = {
    permissions: PlatformPermission[];
};

export type DeveloperContext = {
    active: boolean;
};

export type DeveloperPermission =
    | 'create_blocks'
    | 'create_templates'
    | 'submit_marketplace_item';

export type DeveloperPermissionOption = {
    value: DeveloperPermission;
    label: string;
    short_label: string;
};
