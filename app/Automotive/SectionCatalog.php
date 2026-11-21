<?php

namespace App\Automotive;

use App\Enums\SectionPageRole;
use App\Enums\SiteSectionType;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteSection;

/**
 * Vehicle sections of a Site for the draft runtimes (Designer, preview): template Pages, addresses
 * and page context (ADR-011 §3–§5). Catalog / Card use Site vehicles; Used / Specials use Stock.
 *
 * Catalog (D-131): `{prefix}/{mark}/{slug}`. Stock (X-038): `{prefix}/{mark}/{model}/{path_key}`.
 *
 * @phpstan-import-type VehiclePage from VehiclePages
 *
 * @phpstan-type PageContext array{role: string, mark: string|null, model: string|null, vehicle: string|null}
 * @phpstan-type CatalogProps array{public_id: string, title: string, prefix: string, root: bool, enabled: bool, source: string, pages: array<string, string>, vehicles: list<VehiclePage>}
 */
final class SectionCatalog
{
    public function __construct(
        private SectionPages $pages,
        private VehiclePages $vehiclePages,
        private StockVehiclePages $stockPages,
    ) {}

    /**
     * Default section for ordinary Pages: prefer Catalog / Card, otherwise any section (Used / Specials).
     */
    public function section(Site $site): ?SiteSection
    {
        $prefer = array_map(fn (SiteSectionType $type): string => $type->value, SiteSectionType::vehicleSections());

        return $site->sections()
            ->whereIn('type', $prefer)
            ->orderBy('id')
            ->first()
            ?? $site->sections()->orderBy('id')->first();
    }

    /**
     * Enabled sections in Site order.
     *
     * @return list<SiteSection>
     */
    public function enabled(Site $site): array
    {
        return array_values($site->sections()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all());
    }

    /**
     * @return CatalogProps|null
     */
    public function props(Site $site, ?SiteSection $section = null): ?array
    {
        $section ??= $this->section($site);

        if ($section === null) {
            return null;
        }

        $this->pages->ensure($section);

        return [
            'public_id' => $section->public_id,
            'title' => $section->title,
            'prefix' => $section->prefix,
            'root' => $section->type->atRoot(),
            'enabled' => $section->enabled,
            'source' => $section->type->fromStock() ? 'stock' : 'site',
            'pages' => $section->pages()->get()
                ->mapWithKeys(fn (Page $page): array => [$page->section_role->value => $page->public_id])
                ->all(),
            'vehicles' => $section->type->fromStock()
                ? $this->stockPages->forSection($section)
                : $this->vehiclePages->forSite($site),
        ];
    }

    /**
     * @return list<CatalogProps>
     */
    public function propsAll(Site $site, bool $enabledOnly = true): array
    {
        $items = [];
        $sections = $enabledOnly
            ? $this->enabled($site)
            : array_values($site->sections()->orderBy('sort_order')->orderBy('id')->get()->all());

        foreach ($sections as $section) {
            $props = $this->props($site, $section);

            if ($props !== null) {
                $items[] = $props;
            }
        }

        return $items;
    }

    /**
     * @param  CatalogProps  $catalog
     * @return PageContext
     */
    public static function sampleContext(Page $page, array $catalog, ?string $sample): array
    {
        $role = $page->section_role ?? SectionPageRole::Index;
        $vehicle = collect($catalog['vehicles'])->firstWhere('public_id', $sample) ?? ($catalog['vehicles'][0] ?? null);

        if ($role === SectionPageRole::Index || $vehicle === null) {
            return ['role' => $role->value, 'mark' => null, 'model' => null, 'vehicle' => null];
        }

        return [
            'role' => $role->value,
            'mark' => $vehicle['mark']['segment'],
            'model' => $role === SectionPageRole::Mark ? null : $vehicle['model']['segment'],
            'vehicle' => $role === SectionPageRole::Item ? $vehicle['public_id'] : null,
        ];
    }

    /**
     * @param  CatalogProps  $catalog
     * @param  list<string>  $segments
     * @return array{role: SectionPageRole, context: PageContext}|null
     */
    public static function resolve(array $catalog, array $segments): ?array
    {
        if ($catalog['root']) {
            $item = count($segments) === 1 ? collect($catalog['vehicles'])->firstWhere('slug', $segments[0]) : null;

            return $item === null ? null : [
                'role' => SectionPageRole::Item,
                'context' => [
                    'role' => SectionPageRole::Item->value,
                    'mark' => $item['mark']['segment'],
                    'model' => $item['model']['segment'],
                    'vehicle' => $item['public_id'],
                ],
            ];
        }

        if (($segments[0] ?? null) !== $catalog['prefix']) {
            return null;
        }

        return $catalog['source'] === 'stock'
            ? self::resolveStock($catalog, $segments)
            : self::resolveCatalog($catalog, $segments);
    }

    /**
     * Catalog (D-131): prefix / mark / slug.
     *
     * @param  CatalogProps  $catalog
     * @param  list<string>  $segments
     * @return array{role: SectionPageRole, context: PageContext}|null
     */
    private static function resolveCatalog(array $catalog, array $segments): ?array
    {
        if (count($segments) > 3) {
            return null;
        }

        [, $mark, $slug] = array_pad($segments, 3, null);
        $vehicles = collect($catalog['vehicles']);

        if ($mark !== null) {
            $vehicles = $vehicles->filter(fn (array $vehicle): bool => $vehicle['mark']['segment'] === $mark);
        }

        $item = $slug === null ? null : $vehicles->firstWhere('slug', $slug);

        if (($mark !== null && $vehicles->isEmpty()) || ($slug !== null && $item === null)) {
            return null;
        }

        $role = match (true) {
            $slug !== null => SectionPageRole::Item,
            $mark !== null => SectionPageRole::Mark,
            default => SectionPageRole::Index,
        };

        return [
            'role' => $role,
            'context' => [
                'role' => $role->value,
                'mark' => $mark,
                'model' => $item['model']['segment'] ?? null,
                'vehicle' => $item['public_id'] ?? null,
            ],
        ];
    }

    /**
     * Stock Used/Specials: prefix / mark / model / path_key.
     *
     * @param  CatalogProps  $catalog
     * @param  list<string>  $segments
     * @return array{role: SectionPageRole, context: PageContext}|null
     */
    private static function resolveStock(array $catalog, array $segments): ?array
    {
        if (count($segments) > 4) {
            return null;
        }

        [, $mark, $model, $slug] = array_pad($segments, 4, null);
        $vehicles = collect($catalog['vehicles']);

        if ($mark !== null) {
            $vehicles = $vehicles->filter(fn (array $vehicle): bool => $vehicle['mark']['segment'] === $mark);
        }

        if ($model !== null) {
            $vehicles = $vehicles->filter(fn (array $vehicle): bool => $vehicle['model']['segment'] === $model);
        }

        $item = $slug === null ? null : $vehicles->firstWhere('slug', $slug);

        if (($mark !== null && $vehicles->isEmpty()) || ($model !== null && $vehicles->isEmpty()) || ($slug !== null && $item === null)) {
            return null;
        }

        $role = match (true) {
            $slug !== null => SectionPageRole::Item,
            $model !== null => SectionPageRole::Model,
            $mark !== null => SectionPageRole::Mark,
            default => SectionPageRole::Index,
        };

        return [
            'role' => $role,
            'context' => [
                'role' => $role->value,
                'mark' => $mark,
                'model' => $model,
                'vehicle' => $item['public_id'] ?? null,
            ],
        ];
    }

    /**
     * @param  list<string>  $segments
     * @return array{catalog: CatalogProps, role: SectionPageRole, context: PageContext}|null
     */
    public function resolvePath(Site $site, array $segments): ?array
    {
        foreach ($this->propsAll($site) as $catalog) {
            if (! $catalog['enabled']) {
                continue;
            }

            $resolved = self::resolve($catalog, $segments);

            if ($resolved !== null) {
                return ['catalog' => $catalog, ...$resolved];
            }
        }

        return null;
    }

    /**
     * @return CatalogProps|null
     */
    public function propsForPage(Site $site, Page $page): ?array
    {
        if (! $page->isSectionPage() || $page->site_section_id === null) {
            return $this->props($site);
        }

        $section = $site->sections()->whereKey($page->site_section_id)->first();

        return $section === null ? null : $this->props($site, $section);
    }
}
