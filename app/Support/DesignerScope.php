<?php

namespace App\Support;

use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;

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
}
