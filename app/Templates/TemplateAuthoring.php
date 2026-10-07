<?php

namespace App\Templates;

use App\Enums\SiteType;
use App\Enums\TemplateOwnerScope;
use App\Models\DeveloperProfile;
use App\Models\Page;
use App\Models\Template;
use App\Models\TemplatePage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates Templates with explicit ownership and edits their settings (P9-007). A new Template
 * starts with an empty home Page and is not offered for Site creation (installation is P9-015).
 */
final class TemplateAuthoring
{
    public function __construct(private TemplateAuthoringAuthorization $authorization) {}

    /**
     * @param  list<SiteType>  $siteTypes
     */
    public function createPlatform(User $actor, string $name, string $slug, array $siteTypes): Template
    {
        $this->authorize($this->authorization->canAuthorPlatformTemplates($actor));

        return $this->create($actor, $name, $slug, $siteTypes, null);
    }

    /**
     * @param  list<SiteType>  $siteTypes
     */
    public function createDeveloper(User $actor, string $name, string $slug, array $siteTypes): Template
    {
        $profile = $this->authorization->developerAuthor($actor);
        $this->authorize($profile !== null);

        return $this->create($actor, $name, $slug, $siteTypes, $profile);
    }

    /**
     * @param  list<SiteType>  $siteTypes
     */
    public function updateSettings(User $actor, Template $template, string $name, array $siteTypes): void
    {
        $this->authorize($this->authorization->canEdit($actor, $template));

        $template->name = $name;
        $template->site_types = self::values($siteTypes);

        if (! $template->isDirty()) {
            return;
        }

        $template->lastEditor()->associate($actor);
        $template->save();
        $this->log('template_updated', $actor, $template);
    }

    /**
     * @param  array<string, string>  $extra
     */
    public function log(string $action, User $actor, Template $template, array $extra = []): void
    {
        $prefix = $template->isPlatformOwned() ? 'platform' : 'developer';

        Log::info("{$prefix}.{$action}", [
            'template' => $template->public_id,
            'owner_scope' => $template->owner_scope->value,
            ...$extra,
            'actor_user_id' => $actor->id,
        ]);
    }

    /**
     * @param  list<SiteType>  $siteTypes
     */
    private function create(User $actor, string $name, string $slug, array $siteTypes, ?DeveloperProfile $profile): Template
    {
        $template = DB::transaction(function () use ($actor, $name, $slug, $siteTypes, $profile): Template {
            $template = new Template(['name' => $name, 'slug' => $slug]);
            $template->owner_scope = $profile === null ? TemplateOwnerScope::Platform : TemplateOwnerScope::Developer;
            $template->developerProfile()->associate($profile);
            $template->site_types = self::values($siteTypes);
            $template->is_official = false;
            $template->creator()->associate($actor);
            $template->lastEditor()->associate($actor);
            $template->save();

            $home = new TemplatePage(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
            $home->is_home = true;
            $home->template()->associate($template);
            $home->save();

            return $template;
        });

        $this->log('template_created', $actor, $template);

        return $template;
    }

    /**
     * @param  list<SiteType>  $siteTypes
     * @return list<string>
     */
    private static function values(array $siteTypes): array
    {
        return array_values(array_map(
            fn (SiteType $type): string => $type->value,
            array_filter(SiteType::cases(), fn (SiteType $type): bool => in_array($type, $siteTypes, true)),
        ));
    }

    private function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
