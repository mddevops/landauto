export type PlatformPermission =
    | 'view_catalog'
    | 'edit_catalog'
    | 'manage_catalog_media';

export type PlatformContext = {
    permissions: PlatformPermission[];
};
