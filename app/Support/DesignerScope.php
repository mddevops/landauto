<?php

namespace App\Support;

use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;

/**
 * Designer resources are addressed only inside the current Workspace and only on Sites the member
 * may enter (D-088); anything else is reported as missing so foreign public IDs are never confirmed.
 */
final class DesignerScope
{
    public function __construct(
        private WorkspaceContext $workspaceContext,
        private SiteAccessResolver $siteAccess,
    ) {}

    public function site(Site $site): Site
    {
        abort_unless(
            $site->workspace_id === $this->workspaceContext->current()?->id
                && $this->siteAccess->canAccess($this->workspaceContext->membership(), $site),
            404,
        );

        return $site;
    }

    public function page(Site $site, Page $page): Page
    {
        $this->site($site);
        abort_unless($page->site_id === $site->id, 404);

        return $page;
    }

    public function block(Site $site, BlockInstance $block): BlockInstance
    {
        $this->site($site);
        abort_unless($block->page->site_id === $site->id, 404);

        return $block;
    }

    public function asset(Site $site, SiteAsset $asset): SiteAsset
    {
        $this->site($site);
        abort_unless($asset->site_id === $site->id, 404);

        return $asset;
    }

    public function vehicle(Site $site, SiteVehicle $vehicle): SiteVehicle
    {
        $this->site($site);
        abort_unless($vehicle->site_id === $site->id, 404);

        return $vehicle;
    }

    public function form(Site $site, Form $form): Form
    {
        $this->site($site);
        abort_unless($form->site_id === $site->id, 404);

        return $form;
    }

    public function popup(Site $site, Popup $popup): Popup
    {
        $this->site($site);
        abort_unless($popup->site_id === $site->id, 404);

        return $popup;
    }

    public function offer(Site $site, SiteOffer $offer): SiteOffer
    {
        $this->site($site);
        abort_unless($offer->vehicle->site_id === $site->id, 404);

        return $offer;
    }
}
