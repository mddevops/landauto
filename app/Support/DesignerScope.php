<?php

namespace App\Support;

use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteAsset;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;

/**
 * Designer resources are addressed only inside the current Workspace; anything else is
 * reported as missing so foreign public IDs are never confirmed.
 */
final class DesignerScope
{
    public function __construct(private WorkspaceContext $workspaceContext) {}

    public function site(Site $site): Site
    {
        abort_unless($site->workspace_id === $this->workspaceContext->current()?->id, 404);

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

    public function offer(Site $site, SiteOffer $offer): SiteOffer
    {
        $this->site($site);
        abort_unless($offer->vehicle->site_id === $site->id, 404);

        return $offer;
    }
}
