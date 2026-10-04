<?php

namespace App\Policies;

use App\Enums\WorkspacePermission;
use App\Models\Site;
use App\Models\User;
use App\Support\WorkspaceAuthorization;

final class SitePolicy
{
    public function __construct(private WorkspaceAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->allows($user, WorkspacePermission::ViewSite);
    }

    public function view(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace(
            $user,
            $site->workspace,
            WorkspacePermission::ViewSite,
        );
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, WorkspacePermission::CreateSites);
    }

    public function update(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace(
            $user,
            $site->workspace,
            WorkspacePermission::EditSiteSettings,
        );
    }

    public function editDesign(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditDesign);
    }

    public function editContent(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditContent);
    }

    public function preview(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::PreviewSite);
    }

    public function manageAssets(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::ManageAssets);
    }

    public function editPopups(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditPopups);
    }

    public function editForms(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditForms);
    }

    public function viewVehicles(User $user, Site $site): bool
    {
        foreach ([WorkspacePermission::ViewVehicles, WorkspacePermission::EditVehicles, WorkspacePermission::EditPrices, WorkspacePermission::EditBenefits] as $permission) {
            if ($this->authorization->allowsForWorkspace($user, $site->workspace, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function editVehicles(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditVehicles);
    }

    public function editPrices(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditPrices);
    }

    public function editBenefits(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace($user, $site->workspace, WorkspacePermission::EditBenefits);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace(
            $user,
            $site->workspace,
            WorkspacePermission::DeleteSite,
        );
    }
}
