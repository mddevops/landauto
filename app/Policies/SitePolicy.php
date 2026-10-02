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

    public function delete(User $user, Site $site): bool
    {
        return $this->authorization->allowsForWorkspace(
            $user,
            $site->workspace,
            WorkspacePermission::DeleteSite,
        );
    }
}
