<?php

namespace App\Templates;

use App\Developers\DeveloperAccess;
use App\Developers\DeveloperAuthorization;
use App\Enums\DeveloperPermission;
use App\Enums\PlatformPermission;
use App\Enums\TemplateOwnerScope;
use App\Models\DeveloperProfile;
use App\Models\Template;
use App\Models\User;
use App\Support\PlatformAuthorization;

/**
 * Central Template authoring authorization (P9-007, D-117 / D-118 like Blocks). Platform Templates
 * need `manage_platform_content`; Developer Templates need the User's own active profile with
 * `create_templates` and only ever cover that profile's Templates. Workspace roles never grant it.
 */
final class TemplateAuthoringAuthorization
{
    public function __construct(
        private PlatformAuthorization $platform,
        private DeveloperAccess $access,
        private DeveloperAuthorization $developers,
    ) {}

    public function canAuthorPlatformTemplates(?User $user): bool
    {
        return $user !== null && $this->platform->allows($user, PlatformPermission::ManagePlatformContent);
    }

    public function developerAuthor(?User $user): ?DeveloperProfile
    {
        $profile = $this->access->activeProfile($user);

        return $profile !== null && $this->developers->allows($profile, DeveloperPermission::CreateTemplates) ? $profile : null;
    }

    public function canEdit(?User $user, Template $template): bool
    {
        return match ($template->owner_scope) {
            TemplateOwnerScope::Platform => $this->canAuthorPlatformTemplates($user),
            TemplateOwnerScope::Developer => $this->developerAuthor($user)?->id === $template->developer_profile_id,
        };
    }
}
