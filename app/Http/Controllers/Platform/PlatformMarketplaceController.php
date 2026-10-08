<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\ManagesMarketplaceListings;
use App\Http\Controllers\Controller;
use App\Models\DeveloperProfile;
use Illuminate\Http\Request;

/**
 * Official Landflow Marketplace Listings (`manage_platform_content`, D-117): platform-owned Blocks
 * and Templates only, no Developer Profile. Developer listings are 404 here.
 */
class PlatformMarketplaceController extends Controller
{
    use ManagesMarketplaceListings;

    protected function owner(Request $request): ?DeveloperProfile
    {
        return null;
    }

    protected function routePrefix(): string
    {
        return 'platform.marketplace';
    }

    protected function pagePrefix(): string
    {
        return 'platform/marketplace';
    }
}
