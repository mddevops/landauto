<?php

namespace App\Support;

use App\Enums\WorkspacePermission;
use App\Enums\WorkspaceRole;

final class WorkspacePermissionResolver
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
                WorkspacePermission::DeleteSite,
                WorkspacePermission::EditDesign,
                WorkspacePermission::EditContent,
                WorkspacePermission::EditForms,
                WorkspacePermission::EditVehicles,
                WorkspacePermission::EditPrices,
                WorkspacePermission::ManageIntegrations,
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
        };
    }

    public function roleAllows(WorkspaceRole $role, WorkspacePermission $permission): bool
    {
        return in_array($permission, $this->forRole($role), true);
    }
}
