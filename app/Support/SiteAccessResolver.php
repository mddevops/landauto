<?php

namespace App\Support;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Builder;
use WeakMap;

/**
 * Single answer to "may this member enter this Site?" (D-088, Phase 8): an active membership of
 * the Site's Workspace, then the member's Site access scope. What the member may do there is
 * still decided only by the Workspace role permissions.
 */
final class SiteAccessResolver
{
    /** @var WeakMap<WorkspaceMember, list<int>> */
    private WeakMap $assignedSiteIds;

    public function __construct()
    {
        $this->assignedSiteIds = new WeakMap;
    }

    /**
     * Roles whose work spans Workspace-scoped resources always see every Site.
     */
    public static function forcesAllSites(WorkspaceRole $role): bool
    {
        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin, WorkspaceRole::IntegrationsManager], true);
    }

    public static function effectiveMode(WorkspaceMember $member): SiteAccessMode
    {
        return self::forcesAllSites($member->role) ? SiteAccessMode::AllSites : $member->site_access_mode;
    }

    public function canAccess(?WorkspaceMember $member, Site $site): bool
    {
        if ($member === null || ! $member->isActive() || $member->workspace_id !== $site->workspace_id) {
            return false;
        }

        if (self::effectiveMode($member) === SiteAccessMode::AllSites) {
            return true;
        }

        return in_array($site->id, $this->assignedSiteIds($member), true);
    }

    /**
     * Restricts a Site query of the member's Workspace to the Sites the member may enter.
     *
     * @param  Builder<Site>  $query
     * @return Builder<Site>
     */
    public function scopeAccessible(Builder $query, WorkspaceMember $member): Builder
    {
        $query->where('workspace_id', $member->workspace_id);

        if (! $member->isActive()) {
            return $query->whereRaw('1 = 0');
        }

        if (self::effectiveMode($member) === SiteAccessMode::AllSites) {
            return $query;
        }

        return $query->whereIn('id', $this->assignedSiteIds($member));
    }

    /**
     * @return list<int>
     */
    private function assignedSiteIds(WorkspaceMember $member): array
    {
        return $this->assignedSiteIds[$member] ??= array_values(array_map(
            'intval',
            $member->sites()->where('sites.workspace_id', $member->workspace_id)->pluck('sites.id')->all(),
        ));
    }
}
