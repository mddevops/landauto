<?php

namespace App\Actions\Sites;

use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Exceptions\SiteLimitReachedException;
use App\Models\Page;
use App\Models\Site;
use App\Models\Template;
use App\Models\Workspace;
use App\Support\WorkspaceEntitlements;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CreateSite
{
    public function __construct(private WorkspaceEntitlements $entitlements) {}

    public function create(Workspace $workspace, Template $template, string $name): Site
    {
        if (! $template->is_official) {
            throw new NotFoundHttpException;
        }

        return DB::transaction(function () use ($workspace, $name): Site {
            $lockedWorkspace = Workspace::query()
                ->whereKey($workspace->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $activeSiteLimit = $this->entitlements->limit($lockedWorkspace, Entitlement::MaxSites);
            $activeSiteCount = $lockedWorkspace->sites()
                ->where('status', SiteStatus::Active->value)
                ->count();

            if ($activeSiteCount >= $activeSiteLimit) {
                throw new SiteLimitReachedException;
            }

            $site = new Site(['name' => $name]);
            $site->workspace()->associate($lockedWorkspace);
            $site->save();

            $homePage = new Page([
                'title' => Page::HOME_TITLE,
                'slug' => Page::HOME_SLUG,
                'sort_order' => 0,
            ]);
            $homePage->is_home = true;
            $homePage->site()->associate($site);
            $homePage->save();

            return $site;
        });
    }
}
