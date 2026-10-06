export type WorkspaceSummary = {
    public_id: string;
    name: string;
};

export type WorkspacePermission =
    | 'view_workspace'
    | 'edit_workspace'
    | 'manage_members'
    | 'manage_roles'
    | 'manage_billing'
    | 'delete_workspace'
    | 'transfer_workspace_ownership'
    | 'create_sites'
    | 'view_site'
    | 'edit_site_settings'
    | 'duplicate_site'
    | 'delete_site'
    | 'edit_design'
    | 'edit_content'
    | 'edit_text'
    | 'edit_images'
    | 'manage_assets'
    | 'edit_popups'
    | 'edit_forms'
    | 'view_vehicles'
    | 'edit_vehicles'
    | 'edit_prices'
    | 'edit_benefits'
    | 'import_vehicles'
    | 'manage_workspace_vehicle_library'
    | 'view_integrations'
    | 'manage_integrations'
    | 'edit_form_routes'
    | 'view_delivery_logs'
    | 'retry_deliveries'
    | 'view_submissions'
    | 'export_submissions'
    | 'edit_seo'
    | 'edit_seo_basic'
    | 'manage_domains'
    | 'preview_site'
    | 'publish_site'
    | 'restore_version';

export type SiteContext = {
    public_id: string;
    name: string;
    can: {
        preview: boolean;
        viewVehicles: boolean;
        viewSubmissions: boolean;
        viewDeliveryLogs: boolean;
        viewIntegrations: boolean;
        editForms: boolean;
        publish: boolean;
        manageDomains: boolean;
        editSeo: boolean;
    };
};

export type WorkspaceContext = {
    current: WorkspaceSummary | null;
    available: WorkspaceSummary[];
    permissions: WorkspacePermission[];
};
