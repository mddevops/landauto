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
use App\Templates\InstallTemplateVersion;
use App\Templates\ProvisionLeadPopup;
use App\Templates\TemplateCatalogAccess;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Site of a fixed type (D-119) in the given Workspace: blank start only for multi-page
 * and landing Sites, a published compatible Template otherwise; every type counts toward
 * `max_sites`. A Template's latest published version is copied into independent Site content
 * (P9-015); access to the Template and its Blocks is checked first (D-121).
 */
final class CreateSite
{
    public function __construct(
        private WorkspaceEntitlements $entitlements,
        private TemplateCatalogAccess $templateAccess,
        private InstallTemplateVersion $installer,
        private ProvisionLeadPopup $leadPopup,
    ) {}

    /**
     * @throws SiteCreationRejectedException
     * @throws SiteLimitReachedException
     */
    public function create(Workspace $workspace, string $name, SiteType $type, ?Template $template = null): Site
    {
        if ($template === null && ! $type->allowsBlankStart()) {
            throw new SiteCreationRejectedException('template', 'Этот формат создаётся только из шаблона. Выберите шаблон.');
        }

        $version = $template?->versions()->latest('id')->first();

        if ($template !== null && $version === null) {
            throw new SiteCreationRejectedException('template', 'Выбранный шаблон недоступен.');
        }

        if ($template !== null && ! $template->supports($type)) {
            throw new SiteCreationRejectedException('template', 'Шаблон не подходит для выбранного формата сайта.');
        }

        if ($version?->content_json !== null && ! $type->allowsPageCreation() && count($version->content_json['pages']) > 1) {
            throw new SiteCreationRejectedException('template', 'Шаблон содержит несколько страниц и подходит только для многостраничного сайта.');
        }

        if ($template !== null && ($denial = $this->templateAccess->installDenial($workspace, $template, $version)) !== null) {
            throw new SiteCreationRejectedException('template', $denial);
        }

        return DB::transaction(function () use ($workspace, $name, $type, $version): Site {
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

            if ($version?->content_json !== null) {
                $this->installer->install($site, $version);

                match ($type) {
                    SiteType::Quiz => $this->leadPopup->provision($site, ProvisionLeadPopup::QUIZ_FORM_NAME),
                    SiteType::ChatSelection => $this->leadPopup->provision($site, ProvisionLeadPopup::CHAT_FORM_NAME),
                    default => null,
                };
            } else {
                $this->createHomePage($site);
            }

            return $site;
        });
    }

    private function createHomePage(Site $site): void
    {
        $homePage = new Page([
            'title' => Page::HOME_TITLE,
            'slug' => Page::HOME_SLUG,
            'sort_order' => 0,
        ]);
        $homePage->is_home = true;
        $homePage->site()->associate($site);
        $homePage->save();
    }
}
