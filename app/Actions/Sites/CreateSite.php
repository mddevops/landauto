<?php

namespace App\Actions\Sites;

use App\Enums\Entitlement;
use App\Enums\SiteStatus;
use App\Enums\SiteType;
use App\Exceptions\SiteCreationRejectedException;
use App\Exceptions\SiteLimitReachedException;
use App\Models\Page;
use App\Models\Site;
use App\Models\Template;
use App\Models\Workspace;
use App\Support\SiteSubdomain;
use App\Support\WorkspaceEntitlements;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Site of a fixed type (D-119) in the given Workspace: blank start only for multi-page
 * and landing Sites, an official compatible Template otherwise; every type counts toward `max_sites`.
 */
final class CreateSite
{
    public function __construct(private WorkspaceEntitlements $entitlements) {}

    /**
     * @throws SiteCreationRejectedException
     * @throws SiteLimitReachedException
     */
    public function create(Workspace $workspace, string $name, SiteType $type, ?Template $template = null): Site
    {
        if ($template === null && ! $type->allowsBlankStart()) {
            throw new SiteCreationRejectedException('template', 'Этот формат создаётся только из шаблона. Выберите шаблон.');
        }

        if ($template !== null && ! $template->is_official) {
            throw new SiteCreationRejectedException('template', 'Выбранный шаблон недоступен.');
        }

        if ($template !== null && ! $template->supports($type)) {
            throw new SiteCreationRejectedException('template', 'Шаблон не подходит для выбранного формата сайта.');
        }

        return DB::transaction(function () use ($workspace, $name, $type): Site {
            $lockedWorkspace = Workspace::query()
                ->whereKey($workspace->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $required = $type->requiredEntitlement();

            if ($required !== null && ! $this->entitlements->allows($lockedWorkspace, $required)) {
                throw new SiteCreationRejectedException('site_type', 'Многостраничные сайты недоступны на текущем тарифе. Выберите лендинг или смените тариф.');
            }

            $activeSiteLimit = $this->entitlements->limit($lockedWorkspace, Entitlement::MaxSites);
            $activeSiteCount = $lockedWorkspace->sites()
                ->where('status', SiteStatus::Active->value)
                ->count();

            if ($activeSiteCount >= $activeSiteLimit) {
                throw new SiteLimitReachedException;
            }

            $site = new Site(['name' => $name]);
            $site->site_type = $type;
            $site->subdomain = SiteSubdomain::suggest($name);
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
