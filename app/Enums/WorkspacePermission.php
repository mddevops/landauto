<?php

namespace App\Enums;

/**
 * Stable Workspace permission catalog. Permission keys are backend capabilities,
 * not browser-controlled role flags or subscription entitlements.
 */
enum WorkspacePermission: string
{
    case ViewWorkspace = 'view_workspace';
    case EditWorkspace = 'edit_workspace';
    case ManageMembers = 'manage_members';
    case ManageRoles = 'manage_roles';
    case ManageBilling = 'manage_billing';
    case DeleteWorkspace = 'delete_workspace';
    case TransferWorkspaceOwnership = 'transfer_workspace_ownership';
    case CreateSites = 'create_sites';
    case ViewSite = 'view_site';
    case EditSiteSettings = 'edit_site_settings';
    case DuplicateSite = 'duplicate_site';
    case DeleteSite = 'delete_site';
    case EditDesign = 'edit_design';
    case EditContent = 'edit_content';
    case EditText = 'edit_text';
    case EditImages = 'edit_images';
    case ManageAssets = 'manage_assets';
    case EditPopups = 'edit_popups';
    case EditForms = 'edit_forms';
    case ViewVehicles = 'view_vehicles';
    case EditVehicles = 'edit_vehicles';
    case EditPrices = 'edit_prices';
    case EditBenefits = 'edit_benefits';
    case ImportVehicles = 'import_vehicles';
    case ManageWorkspaceVehicleLibrary = 'manage_workspace_vehicle_library';
    case ViewIntegrations = 'view_integrations';
    case ManageIntegrations = 'manage_integrations';
    case EditFormRoutes = 'edit_form_routes';
    case ViewDeliveryLogs = 'view_delivery_logs';
    case RetryDeliveries = 'retry_deliveries';
    case ViewSubmissions = 'view_submissions';
    case ExportSubmissions = 'export_submissions';
    case EditSeo = 'edit_seo';
    case EditSeoBasic = 'edit_seo_basic';
    case ManageDomains = 'manage_domains';
    case PreviewSite = 'preview_site';
    case PublishSite = 'publish_site';
    case RestoreVersion = 'restore_version';
}
