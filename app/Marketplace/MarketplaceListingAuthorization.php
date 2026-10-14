<?php

namespace App\Marketplace;

use App\Developers\DeveloperAccess;
use App\Developers\DeveloperAuthorization;
use App\Enums\DeveloperPermission;
use App\Enums\PlatformPermission;
use App\Models\DeveloperProfile;
use App\Models\MarketplaceListing;
use App\Models\User;
use App\Support\PlatformAuthorization;

/**
 * Central Marketplace Listing authorization (D-117, D-118). Official listings need the platform
 * permission `manage_platform_content`; Developer listings need the User's own active Developer
 * Profile with `submit_marketplace_item` (not `create_blocks` / `create_templates`) and only ever
 * cover that profile's listings. Workspace roles never grant either.
 */
final class MarketplaceListingAuthorization
{
    public function __construct(
        private PlatformAuthorization $platform,
        private DeveloperAccess $access,
        private DeveloperAuthorization $developers,
    ) {}

    public function canManagePlatformListings(?User $user): bool
    {
        return $user !== null && $this->platform->allows($user, PlatformPermission::ManagePlatformContent);
    }

    /**
     * The User's own active Developer Profile when it may manage Developer listings.
     */
    public function developerAuthor(?User $user): ?DeveloperProfile
    {
        $profile = $this->access->activeProfile($user);

        return $profile !== null && $this->developers->allows($profile, DeveloperPermission::SubmitMarketplaceItem) ? $profile : null;
    }

    public function canManage(?User $user, MarketplaceListing $listing): bool
    {
        return $listing->isPlatformOwned()
            ? $this->canManagePlatformListings($user)
            : $this->developerAuthor($user)?->id === $listing->developer_profile_id;
    }
}
