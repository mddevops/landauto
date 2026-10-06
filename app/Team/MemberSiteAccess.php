<?php

namespace App\Team;

use App\Enums\SiteAccessMode;
use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\SiteAccessResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Site access scope of members and invitations (D-088). Selected Sites must belong to the same
 * Workspace; `selected_sites` without Sites is invalid; Owner and Admin are always `all_sites`.
 */
final class MemberSiteAccess
{
    public function __construct(private TeamAuthority $authority) {}

    /**
     * Validates the requested scope and returns internal Site IDs to assign (empty for all Sites).
     *
     * @param  list<string>  $sitePublicIds
     * @return list<int>
     */
    public function resolveSites(Workspace $workspace, WorkspaceRole $role, SiteAccessMode $mode, array $sitePublicIds, string $field = 'sites'): array
    {
        if ($mode === SiteAccessMode::AllSites) {
            return [];
        }

        if (SiteAccessResolver::forcesAllSites($role)) {
            throw ValidationException::withMessages([$field => 'Для этой роли всегда открыт доступ ко всем сайтам.']);
        }

        $publicIds = array_values(array_unique(array_map('strtolower', $sitePublicIds)));

        if ($publicIds === []) {
            throw ValidationException::withMessages([$field => 'Выберите хотя бы один сайт.']);
        }

        $siteIds = array_values(array_map('intval', Site::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('public_id', $publicIds)
            ->pluck('id')
            ->all()));

        if (count($siteIds) !== count($publicIds)) {
            throw ValidationException::withMessages([$field => 'Некоторые выбранные сайты недоступны.']);
        }

        return $siteIds;
    }

    /**
     * @param  list<int>  $siteIds  already validated for the member's Workspace
     */
    public function apply(WorkspaceMember $member, SiteAccessMode $mode, array $siteIds): void
    {
        if (SiteAccessResolver::forcesAllSites($member->role)) {
            $mode = SiteAccessMode::AllSites;
        }

        $member->forceFill(['site_access_mode' => $mode])->save();
        $member->sites()->sync($mode === SiteAccessMode::AllSites ? [] : $siteIds);
    }

    /**
     * @param  list<string>  $sitePublicIds
     */
    public function update(Workspace $workspace, WorkspaceMember $actor, string $memberPublicId, SiteAccessMode $mode, array $sitePublicIds): WorkspaceMember
    {
        $member = DB::transaction(function () use ($workspace, $actor, $memberPublicId, $mode, $sitePublicIds): WorkspaceMember {
            $member = WorkspaceMember::query()
                ->where('workspace_id', $workspace->id)
                ->where('public_id', strtolower($memberPublicId))
                ->lockForUpdate()
                ->first() ?? throw new NotFoundHttpException;

            if (! $this->authority->canManageMember($actor, $member)) {
                throw new AccessDeniedHttpException;
            }

            $this->apply($member, $mode, $this->resolveSites($workspace, $member->role, $mode, $sitePublicIds));

            return $member;
        });

        Log::info('workspace.site_access_changed', [
            'workspace' => $workspace->public_id,
            'member' => $member->public_id,
            'actor' => $actor->public_id,
            'mode' => $member->site_access_mode->value,
            'sites' => $member->sites()->count(),
        ]);

        return $member;
    }
}
