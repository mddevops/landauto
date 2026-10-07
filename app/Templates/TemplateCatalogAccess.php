<?php

namespace App\Templates;

use App\Blocks\BlockCatalogAccess;
use App\Enums\CatalogAccessMode;
use App\Models\BlockVersion;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Workspace;
use App\Support\WorkspaceEntitlements;

/**
 * Backend access check for installing a Template into a new Site (D-079, P9-015). The Site does
 * not exist yet, so no Site license can apply: the Template and each included Block must be
 * usable by the Workspace on their own (free, or `entitlement` with the typed entitlement).
 */
final class TemplateCatalogAccess
{
    public function __construct(
        private WorkspaceEntitlements $entitlements,
        private BlockCatalogAccess $blocks,
    ) {}

    /**
     * Russian reason why the Workspace may not create a Site from the Template; null when it may.
     */
    public function installDenial(Workspace $workspace, Template $template, ?TemplateVersion $version): ?string
    {
        $denial = match ($template->access_mode) {
            CatalogAccessMode::Free => null,
            CatalogAccessMode::Entitlement => $template->access_entitlement !== null && $this->entitlements->allows($workspace, $template->access_entitlement)
                ? null
                : "Шаблон доступен на тарифе с опцией «{$template->access_entitlement?->label()}».",
            CatalogAccessMode::Paid => 'Платный шаблон: нужна лицензия для сайта. Покупка в Landflow пока недоступна.',
            CatalogAccessMode::AdminGrant => 'Шаблон выдаёт администратор Landflow для конкретного сайта; создать из него новый сайт нельзя.',
        };

        if ($denial !== null || $version === null) {
            return $denial;
        }

        $versions = BlockVersion::query()
            ->with('definition')
            ->whereIn('id', TemplateVersion::blockVersionIds($version->content_json))
            ->get();

        foreach ($versions as $blockVersion) {
            $blockDenial = $this->blocks->workspaceDenial($workspace, $blockVersion->definition);

            if ($blockDenial !== null) {
                return "Шаблон содержит блок «{$blockVersion->definition->name}». {$blockDenial}";
            }
        }

        return null;
    }
}
