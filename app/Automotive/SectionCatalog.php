<?php

namespace App\Automotive;

use App\Enums\SectionPageRole;
use App\Enums\SiteSectionType;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteSection;

/**
 * Catalog section of a Site for the draft runtimes (Designer, preview): its template Pages, the
 * address of every vehicle and the page context a template Page renders with (ADR-011 §3–§5).
 *
 * @phpstan-import-type VehiclePage from VehiclePages
 *
 * @phpstan-type PageContext array{role: string, mark: string|null, vehicle: string|null}
 * @phpstan-type CatalogProps array{public_id: string, title: string, prefix: string, enabled: bool, pages: array<string, string>, vehicles: list<VehiclePage>}
 */
final class SectionCatalog
{
    public function __construct(
        private SectionPages $pages,
        private VehiclePages $vehiclePages,
    ) {}

    public function section(Site $site): ?SiteSection
    {
        return $site->sections()->where('type', SiteSectionType::Catalog->value)->first();
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
            'enabled' => $section->enabled,
            'pages' => $section->pages()->get()
                ->mapWithKeys(fn (Page $page): array => [$page->section_role->value => $page->public_id])
                ->all(),
            'vehicles' => $this->vehiclePages->forSite($site),
        ];
    }

    /**
     * Context of a template Page shown on the example of one vehicle (`sample`, Designer) or the
     * first vehicle of the section.
     *
     * @param  CatalogProps  $catalog
     * @return PageContext
     */
    public static function sampleContext(Page $page, array $catalog, ?string $sample): array
    {
        $role = $page->section_role ?? SectionPageRole::Index;
        $vehicle = collect($catalog['vehicles'])->firstWhere('public_id', $sample) ?? ($catalog['vehicles'][0] ?? null);

        if ($role === SectionPageRole::Index || $vehicle === null) {
            return ['role' => $role->value, 'mark' => null, 'vehicle' => null];
        }

        return [
            'role' => $role->value,
            'mark' => $vehicle['mark']['segment'],
            'vehicle' => $role === SectionPageRole::Item ? $vehicle['public_id'] : null,
        ];
    }

    /**
     * Resolves a section address (`catalog/kia/rio-iv-restyling`) to its template Page and
     * context; null when no vehicle of the section matches.
     *
     * @param  CatalogProps  $catalog
     * @param  list<string>  $segments
     * @return array{role: SectionPageRole, context: PageContext}|null
     */
    public static function resolve(array $catalog, array $segments): ?array
    {
        if (($segments[0] ?? null) !== $catalog['prefix'] || count($segments) > 3) {
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
            'context' => ['role' => $role->value, 'mark' => $mark, 'vehicle' => $item['public_id'] ?? null],
        ];
    }
}
