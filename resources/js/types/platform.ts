export type PlatformPermission =
    | 'view_catalog'
    | 'edit_catalog'
    | 'manage_catalog_media'
    | 'manage_developers';

export type PlatformContext = {
    permissions: PlatformPermission[];
};

export type DeveloperContext = {
    active: boolean;
};
