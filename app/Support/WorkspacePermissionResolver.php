<?php

namespace App\Support;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;

class WorkspacePermissionResolver
{
    /**
     * @return list<WorkspacePermission>
     */
    public function forRole(WorkspaceRole $role): array
    {
        return match ($role) {
            WorkspaceRole::Owner => WorkspacePermission::cases(),
            WorkspaceRole::Admin => [
                WorkspacePermission::ManageMembers,
                WorkspacePermission::CreateSites,
                WorkspacePermission::ViewSite,
                WorkspacePermission::DeleteSite,
                WorkspacePermission::EditDesign,
                WorkspacePermission::EditContent,
                WorkspacePermission::EditPopups,
                WorkspacePermission::EditForms,
                WorkspacePermission::ViewVehicles,
                WorkspacePermission::EditVehicles,
                WorkspacePermission::EditPrices,
                WorkspacePermission::EditBenefits,
                WorkspacePermission::ImportVehicles,
                WorkspacePermission::ViewIntegrations,
                WorkspacePermission::ManageIntegrations,
                WorkspacePermission::EditFormRoutes,
                WorkspacePermission::ViewDeliveryLogs,
                WorkspacePermission::RetryDeliveries,
                WorkspacePermission::ViewSubmissions,
                WorkspacePermission::EditSeo,
                WorkspacePermission::ManageDomains,
                WorkspacePermission::PreviewSite,
                WorkspacePermission::PublishSite,
            ],
            WorkspaceRole::Designer => [
                WorkspacePermission::ViewSite,
                WorkspacePermission::EditDesign,
                WorkspacePermission::EditContent,
                WorkspacePermission::ManageAssets,
                WorkspacePermission::EditPopups,
                WorkspacePermission::PreviewSite,
            ],
            WorkspaceRole::ContentEditor => [
                WorkspacePermission::ViewSite,
                WorkspacePermission::EditContent,
                WorkspacePermission::EditText,
                WorkspacePermission::EditImages,
                WorkspacePermission::EditSeoBasic,
            ],
            WorkspaceRole::PricingManager => [
                WorkspacePermission::ViewSite,
                WorkspacePermission::ViewVehicles,
                WorkspacePermission::EditPrices,
                WorkspacePermission::EditBenefits,
            ],
            // No export_submissions while D-094 is open.
            WorkspaceRole::LeadManager => [
                WorkspacePermission::ViewSite,
                WorkspacePermission::ViewSubmissions,
                WorkspacePermission::ViewDeliveryLogs,
                WorkspacePermission::RetryDeliveries,
            ],
            // Delivery metadata only: lead contents need view_submissions.
            WorkspaceRole::IntegrationsManager => [
                WorkspacePermission::ViewSite,
                WorkspacePermission::ViewIntegrations,
                WorkspacePermission::ManageIntegrations,
                WorkspacePermission::EditFormRoutes,
                WorkspacePermission::ViewDeliveryLogs,
                WorkspacePermission::RetryDeliveries,
            ],
            WorkspaceRole::Publisher => [
                WorkspacePermission::ViewSite,
                WorkspacePermission::PreviewSite,
                WorkspacePermission::PublishSite,
            ],
        };
    }

    public function roleAllows(WorkspaceRole $role, WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->forRole($role), true);
    }
}
