<?php

namespace App\Blocks;

use App\Developers\DeveloperAccess;
use App\Developers\DeveloperAuthorization;
use App\Enums\BlockOwnerScope;
use App\Enums\DeveloperPermission;
use App\Enums\PlatformPermission;
use App\Models\BlockDefinition;
use App\Models\DeveloperProfile;
use App\Models\User;
use App\Support\PlatformAuthorization;

/**
 * Central Block authoring authorization (D-117, D-118). Platform Blocks need the platform
 * permission `manage_platform_content` and no Developer Profile; Developer Blocks need the User's
 * own active profile with `create_blocks` and only ever cover that profile's Blocks. Workspace roles
 * never grant authoring, and Workspace-private authoring does not exist yet.
 */
final class BlockAuthoringAuthorization
{
    public function __construct(
        private PlatformAuthorization $platform,
        private DeveloperAccess $access,
        private DeveloperAuthorization $developers,
    ) {}

    public function canAuthorPlatformBlocks(?User $user): bool
    {
        return $user !== null && $this->platform->allows($user, PlatformPermission::ManagePlatformContent);
    }

    /**
     * The User's own active Developer Profile when it may author Developer Blocks.
     */
    public function developerAuthor(?User $user): ?DeveloperProfile
    {
        $profile = $this->access->activeProfile($user);

        return $profile !== null && $this->developers->allows($profile, DeveloperPermission::CreateBlocks) ? $profile : null;
    }

    public function canEdit(?User $user, BlockDefinition $block): bool
    {
        return match ($block->owner_scope) {
            BlockOwnerScope::Platform => $this->canAuthorPlatformBlocks($user),
            BlockOwnerScope::Developer => $this->developerAuthor($user)?->id === $block->developer_profile_id,
            BlockOwnerScope::WorkspacePrivate => false,
        };
    }

    public function canApproveNative(?User $user, BlockDefinition $block): bool
    {
        if ($user === null || ! $this->canEdit($user, $block)) {
            return false;
        }

        return match ($block->owner_scope) {
            BlockOwnerScope::Platform => $this->platform->allows($user, PlatformPermission::ManagePlatformContent)
                && $this->platform->allows($user, PlatformPermission::ApproveNativeBlocks),
            BlockOwnerScope::Developer => ($profile = $this->developerAuthor($user)) !== null
                && $profile->id === $block->developer_profile_id
                && $this->developers->allows($profile, DeveloperPermission::ApproveNativeBlocks),
            BlockOwnerScope::WorkspacePrivate => false,
        };
    }
}
