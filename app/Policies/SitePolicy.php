<?php

namespace App\Policies;

use App\Enums\WorkspacePermission;
use App\Models\Site;
use App\Models\User;
use App\Support\WorkspaceAuthorization;

/**
 * Every Site ability combines the Workspace role permission with the member's Site access
 * (D-088) through WorkspaceAuthorization::allowsForSite.
 */
final class SitePolicy
{
    public function __construct(private WorkspaceAuthorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->allows($user, WorkspacePermission::ViewSite);
    }

    public function view(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ViewSite);
    }

    public function create(User $user): bool
    {
        return $this->authorization->allows($user, WorkspacePermission::CreateSites);
    }

    public function update(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditSiteSettings);
    }

    public function editDesign(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditDesign);
    }

    /**
     * Adding, removing, duplicating, hiding or reordering Blocks; Quiz / Chat Sites keep the
     * Template structure (D-119).
     */
    public function editStructure(User $user, Site $site): bool
    {
        return ! $site->site_type->hasLockedStructure() && $this->editDesign($user, $site);
    }

    /**
     * Only multi-page Sites get additional Pages (D-119).
     */
    public function addPage(User $user, Site $site): bool
    {
        return $site->site_type->allowsPageCreation() && $this->editDesign($user, $site);
    }

    public function editContent(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditContent);
    }

    public function preview(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::PreviewSite);
    }

    public function manageAssets(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ManageAssets);
    }

    public function editPopups(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditPopups);
    }

    public function editForms(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditForms);
    }

    public function publish(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::PublishSite);
    }

    /**
     * Page title and description: full SEO or the basic SEO permission of Content Editors.
     */
    public function editSeo(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditSeo, WorkspacePermission::EditSeoBasic);
    }

    /**
     * Indexing directives need full SEO rights.
     */
    public function editSeoIndexing(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditSeo);
    }

    public function manageDomains(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ManageDomains);
    }

    public function restoreVersion(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::RestoreVersion);
    }

    public function viewSubmissions(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ViewSubmissions);
    }

    public function viewIntegrations(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ViewIntegrations, WorkspacePermission::ManageIntegrations);
    }

    public function manageIntegrations(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ManageIntegrations);
    }

    public function editFormRoutes(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditFormRoutes);
    }

    public function viewDeliveryLogs(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ViewDeliveryLogs);
    }

    public function retryDeliveries(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::RetryDeliveries);
    }

    public function viewVehicles(User $user, Site $site): bool
    {
        return $this->can(
            $user,
            $site,
            WorkspacePermission::ViewVehicles,
            WorkspacePermission::EditVehicles,
            WorkspacePermission::EditPrices,
            WorkspacePermission::EditBenefits,
        );
    }

    public function editVehicles(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditVehicles);
    }

    public function importVehicles(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::ImportVehicles);
    }

    public function editPrices(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditPrices);
    }

    public function editBenefits(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::EditBenefits);
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->can($user, $site, WorkspacePermission::DeleteSite);
    }

    /**
     * True when any of the given permissions is granted for this Site.
     */
    private function can(User $user, Site $site, WorkspacePermission ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->authorization->allowsForSite($user, $site, $permission)) {
                return true;
            }
        }

        return false;
    }
}
