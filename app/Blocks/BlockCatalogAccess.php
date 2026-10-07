<?php

namespace App\Blocks;

use App\Enums\CatalogAccessMode;
use App\Models\BlockDefinition;
use App\Models\BlockVersion;
use App\Models\Site;
use App\Models\SiteLicense;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Workspace;
use App\Support\Money;
use App\Support\WorkspaceEntitlements;

/**
 * Backend check whether a Site may use a catalog Block (D-079): when adding a Block Instance,
 * installing a Template and publishing, never only when rendering a card. A Site license (for the
 * Block, or for a Template that includes it) grants access for that Site only; a typed entitlement
 * grants access only in `entitlement` mode. No plan-name checks.
 */
final class BlockCatalogAccess
{
    public function __construct(private WorkspaceEntitlements $entitlements) {}

    /**
     * Russian reason why the Site may not use the Block; null when it may.
     *
     * @param  array<int, true>|null  $licensed  preloaded {@see licensedBlockIds()} for batches
     */
    public function denial(Site $site, BlockDefinition $block, ?array $licensed = null): ?string
    {
        if ($block->isWorkspacePrivate()) {
            return 'Этот блок недоступен.';
        }

        if ($block->access_mode === CatalogAccessMode::Free) {
            return null;
        }

        $licensed ??= $this->licensedBlockIds($site);

        return isset($licensed[$block->id]) ? null : $this->workspaceDenial($site->workspace, $block);
    }

    /**
     * Access of a Workspace without any Site license, e.g. for a Site that does not exist yet.
     */
    public function workspaceDenial(Workspace $workspace, BlockDefinition $block): ?string
    {
        if ($block->isWorkspacePrivate()) {
            return 'Этот блок недоступен.';
        }

        return match ($block->access_mode) {
            CatalogAccessMode::Free => null,
            CatalogAccessMode::Entitlement => $block->access_entitlement !== null && $this->entitlements->allows($workspace, $block->access_entitlement)
                ? null
                : "Блок доступен на тарифе с опцией «{$block->access_entitlement?->label()}».",
            CatalogAccessMode::Paid => 'Платный блок: нужна лицензия для этого сайта. Покупка в Landflow пока недоступна.',
            CatalogAccessMode::AdminGrant => 'Блок выдаёт администратор Landflow для конкретного сайта.',
        };
    }

    /**
     * Block Definitions licensed to the Site directly or through a licensed Template (D-079).
     *
     * @return array<int, true>
     */
    public function licensedBlockIds(Site $site): array
    {
        $licenses = $site->licenses()->get(['block_definition_id', 'template_id']);
        $ids = array_values(array_filter($licenses->map(fn (SiteLicense $license): ?int => $license->block_definition_id)->all()));
        $templateIds = array_values(array_filter($licenses->map(fn (SiteLicense $license): ?int => $license->template_id)->all()));

        if ($templateIds !== []) {
            $ids = [...$ids, ...self::templateBlockIds($templateIds)];
        }

        return array_fill_keys($ids, true);
    }

    /**
     * Block Definitions included in any published version of the Templates.
     *
     * @param  list<int>  $templateIds
     * @return list<int>
     */
    public static function templateBlockIds(array $templateIds): array
    {
        $versionIds = TemplateVersion::query()
            ->whereIn('template_id', $templateIds)
            ->whereNotNull('content_json')
            ->get(['id', 'template_id', 'content_json'])
            ->flatMap(fn (TemplateVersion $version): array => TemplateVersion::blockVersionIds($version->content_json))
            ->unique()
            ->values()
            ->all();

        return $versionIds === [] ? [] : array_values(BlockVersion::query()
            ->whereIn('id', $versionIds)
            ->distinct()
            ->pluck('block_definition_id')
            ->all());
    }

    /**
     * Public access card of a catalog item.
     *
     * @return array{mode: string, restricted: bool, label: string, detail: string|null}
     */
    public static function card(BlockDefinition|Template $item): array
    {
        return [
            'mode' => $item->access_mode->value,
            'restricted' => $item->access_mode !== CatalogAccessMode::Free,
            'label' => $item->access_mode->label(),
            'detail' => match ($item->access_mode) {
                CatalogAccessMode::Entitlement => "Опция тарифа: {$item->access_entitlement?->label()}",
                CatalogAccessMode::Paid => $item->price_minor !== null
                    ? Money::format($item->price_minor, $item->price_currency ?? Money::DEFAULT_CURRENCY).' за сайт'
                    : null,
                CatalogAccessMode::Free, CatalogAccessMode::AdminGrant => null,
            },
        ];
    }
}
