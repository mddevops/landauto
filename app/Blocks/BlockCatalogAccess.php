<?php

namespace App\Blocks;

use App\Enums\CatalogAccessMode;
use App\Models\BlockDefinition;
use App\Models\Site;
use App\Support\Money;
use App\Support\WorkspaceEntitlements;

/**
 * Backend check whether a Site may use a catalog Block (D-079): when adding a Block Instance and
 * when publishing, never only when rendering a card. A Site license always grants access for that
 * Site only; a typed entitlement grants access only in `entitlement` mode. No plan-name checks.
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

        $hasLicense = $licensed !== null
            ? isset($licensed[$block->id])
            : $site->licenses()->where('block_definition_id', $block->id)->exists();

        if ($hasLicense) {
            return null;
        }

        return match ($block->access_mode) {
            CatalogAccessMode::Entitlement => $block->access_entitlement !== null && $this->entitlements->allows($site->workspace, $block->access_entitlement)
                ? null
                : "Блок доступен на тарифе с опцией «{$block->access_entitlement?->label()}».",
            CatalogAccessMode::Paid => 'Платный блок: нужна лицензия для этого сайта. Покупка в Landflow пока недоступна.',
            CatalogAccessMode::AdminGrant => 'Блок выдаёт администратор Landflow для конкретного сайта.',
        };
    }

    /**
     * @return array<int, true>
     */
    public function licensedBlockIds(Site $site): array
    {
        return array_fill_keys($site->licenses()->pluck('block_definition_id')->all(), true);
    }

    /**
     * Public access card of a catalog item.
     *
     * @return array{mode: string, restricted: bool, label: string, detail: string|null}
     */
    public static function card(BlockDefinition $block): array
    {
        return [
            'mode' => $block->access_mode->value,
            'restricted' => $block->access_mode !== CatalogAccessMode::Free,
            'label' => $block->access_mode->label(),
            'detail' => match ($block->access_mode) {
                CatalogAccessMode::Entitlement => "Опция тарифа: {$block->access_entitlement?->label()}",
                CatalogAccessMode::Paid => $block->price_minor !== null
                    ? Money::format($block->price_minor, $block->price_currency ?? Money::DEFAULT_CURRENCY).' за сайт'
                    : null,
                CatalogAccessMode::Free, CatalogAccessMode::AdminGrant => null,
            },
        ];
    }
}
