<?php

namespace App\Templates;

use App\Blocks\BlockCatalogAccess;
use App\Enums\CatalogAccessMode;
use App\Models\BlockVersion;
use App\Models\CatalogLicense;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Workspace;
use App\Support\WorkspaceEntitlements;

/**
 * Backend access check for installing a Template Version into a new Site (D-121, P9-015). The Site
 * does not exist yet, so only Workspace licenses can apply: a restricted Template needs a Workspace
 * license (or its typed entitlement in `entitlement` mode). A Template license covers exactly the
 * Block Versions of the version being installed; otherwise each included Block must be usable by
 * the Workspace on its own.
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
        $licensed = CatalogLicense::query()
            ->where('workspace_id', $workspace->id)
            ->where('template_id', $template->id)
            ->exists();

        $denial = $licensed ? null : match ($template->access_mode) {
            CatalogAccessMode::Free => null,
            CatalogAccessMode::Entitlement => $template->access_entitlement !== null && $this->entitlements->allows($workspace, $template->access_entitlement)
                ? null
                : "Шаблон доступен на тарифе с опцией «{$template->access_entitlement?->label()}».",
            CatalogAccessMode::Paid => 'Платный шаблон: для нового сайта нужна лицензия на всё пространство. Покупка в Landflow пока недоступна.',
            CatalogAccessMode::AdminGrant => 'Шаблон выдаёт администратор Landflow; для нового сайта нужна лицензия на всё пространство.',
        };

        if ($denial !== null || $licensed || $version === null) {
            return $denial;
        }

        $versions = BlockVersion::query()
            ->with('definition')
            ->whereIn('id', TemplateVersion::blockVersionIds($version->content_json))
            ->get();
        $licensedBlocks = $this->blocks->workspaceLicensedBlockIds($workspace);

        foreach ($versions as $blockVersion) {
            $blockDenial = $this->blocks->workspaceDenial($workspace, $blockVersion->definition, $licensedBlocks);

            if ($blockDenial !== null) {
                return "Шаблон содержит блок «{$blockVersion->definition->name}». {$blockDenial}";
            }
        }

        return null;
    }
}
