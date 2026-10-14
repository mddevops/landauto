<?php

namespace App\Blocks;

use App\Catalog\PlanCatalogAccess;
use App\Enums\CatalogAccessMode;
use App\Models\BlockDefinition;
use App\Models\CatalogLicense;
use App\Models\Site;
use App\Models\Template;
use App\Models\Workspace;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Backend check whether a Site may newly acquire a catalog Block (D-121): when adding a Block
 * Instance, installing a Template and publishing a version the Site holds no grant for, never only
 * when rendering a card. A license for the Site itself or for its Workspace grants access; an
 * explicit Plan mapping grants access in `entitlement` mode. No plan-name checks. Versions the Site already
 * installed lawfully stay usable through {@see BlockVersionGrants} (D-122).
 */
final class BlockCatalogAccess
{
    public function __construct(private PlanCatalogAccess $plans) {}

    /**
     * Russian reason why the Site may not newly use the Block; null when it may.
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

        return isset($licensed[$block->id]) ? null : $this->modeDenial($site->workspace, $block);
    }

    /**
     * Access of a Workspace through its Workspace licenses only, e.g. for a Site that does not exist yet.
     *
     * @param  array<int, true>|null  $licensed  preloaded {@see workspaceLicensedBlockIds()}
     */
    public function workspaceDenial(Workspace $workspace, BlockDefinition $block, ?array $licensed = null): ?string
    {
        if ($block->isWorkspacePrivate()) {
            return 'Этот блок недоступен.';
        }

        if ($block->access_mode === CatalogAccessMode::Free) {
            return null;
        }

        $licensed ??= $this->workspaceLicensedBlockIds($workspace);

        return isset($licensed[$block->id]) ? null : $this->modeDenial($workspace, $block);
    }

    /**
     * Block Definitions licensed to the Site directly or through its Workspace (D-121).
     *
     * @return array<int, true>
     */
    public function licensedBlockIds(Site $site): array
    {
        return self::blockIds(CatalogLicense::query()->effectiveFor($site));
    }

    /**
     * @return array<int, true>
     */
    public function workspaceLicensedBlockIds(Workspace $workspace): array
    {
        return self::blockIds(CatalogLicense::query()->where('workspace_id', $workspace->id));
    }

    /**
     * Public access card of a catalog item: current acquisition rules, not existing-use rights.
     *
     * @return array{mode: string, restricted: bool, label: string, detail: string|null}
     */
    public static function card(BlockDefinition|Template $item): array
    {
        $planNames = $item->access_mode === CatalogAccessMode::Entitlement
            ? app(PlanCatalogAccess::class)->includedPlanNames($item)
            : [];

        return [
            'mode' => $item->access_mode->value,
            'restricted' => $item->access_mode !== CatalogAccessMode::Free,
            'label' => $item->access_mode->label(),
            'detail' => match ($item->access_mode) {
                CatalogAccessMode::Entitlement => $planNames === [] ? 'Тарифы не назначены' : 'Тарифы: '.implode(', ', $planNames),
                CatalogAccessMode::Paid => self::priceDetail($item),
                CatalogAccessMode::Free, CatalogAccessMode::AdminGrant => null,
            },
        ];
    }

    private function modeDenial(Workspace $workspace, BlockDefinition $block): ?string
    {
        return match ($block->access_mode) {
            CatalogAccessMode::Free => null,
            CatalogAccessMode::Entitlement => $this->plans->includes($workspace, $block)
                ? null
                : $this->plans->denial($block, 'Блок'),
            CatalogAccessMode::Paid => 'Платный блок: нужна лицензия на этот сайт или на всё пространство. Покупка в Landflow пока недоступна.',
            CatalogAccessMode::AdminGrant => 'Блок выдаёт администратор Landflow для сайта или всего пространства.',
        };
    }

    private static function priceDetail(BlockDefinition|Template $item): ?string
    {
        $currency = $item->price_currency ?? Money::DEFAULT_CURRENCY;
        $options = array_filter([
            $item->site_price_minor !== null ? 'Лицензия на 1 сайт — '.Money::format($item->site_price_minor, $currency) : null,
            $item->workspace_price_minor !== null ? 'Лицензия на всё пространство — '.Money::format($item->workspace_price_minor, $currency) : null,
        ]);

        return $options === [] ? null : implode(' · ', $options);
    }

    /**
     * @param  Builder<CatalogLicense>  $licenses
     * @return array<int, true>
     */
    private static function blockIds(Builder $licenses): array
    {
        $ids = $licenses->whereNotNull('block_definition_id')->pluck('block_definition_id')->all();

        return array_fill_keys(array_map(intval(...), $ids), true);
    }
}
