<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Concerns\ManagesMarketplaceListings;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveDeveloperProfile;
use App\Models\DeveloperProfile;
use Illuminate\Http\Request;

/**
 * «Студия» → «Marketplace»: listings of the current User's own Developer Profile products
 * (`submit_marketplace_item`, D-118). Another profile's listing is indistinguishable from a missing one.
 */
class DeveloperMarketplaceController extends Controller
{
    use ManagesMarketplaceListings;

    protected function owner(Request $request): DeveloperProfile
    {
        $profile = $request->attributes->get(EnsureActiveDeveloperProfile::ATTRIBUTE);
        abort_unless($profile instanceof DeveloperProfile, 403);

        return $profile;
    }

    protected function routePrefix(): string
    {
        return 'developer.marketplace';
    }

    protected function pagePrefix(): string
    {
        return 'developer/marketplace';
    }
}
