<?php

namespace App\Domains;

use App\Enums\Entitlement;
use App\Models\Site;
use App\Models\User;
use App\Support\WorkspaceEntitlements;
use Illuminate\Support\Facades\Gate;

/**
 * Custom domains need both the `manage_domains` permission and the typed `custom_domain`
 * entitlement of the Site's Workspace. They stay separate checks; plans are never inspected.
 */
final class CustomDomainAccess
{
    public function __construct(private WorkspaceEntitlements $entitlements) {}

    public function entitled(Site $site): bool
    {
        return $this->entitlements->allows($site->workspace, Entitlement::CustomDomain);
    }

    public function canManage(?User $user, Site $site): bool
    {
        return $user !== null
            && Gate::forUser($user)->allows('manageDomains', $site)
            && $this->entitled($site);
    }
}
