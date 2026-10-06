<?php

namespace App\Enums;

/**
 * Which Sites of the Workspace a member may enter (D-088, Phase 8). Permissions still come only
 * from the Workspace role.
 */
enum SiteAccessMode: string
{
    case AllSites = 'all_sites';
    case SelectedSites = 'selected_sites';
}
