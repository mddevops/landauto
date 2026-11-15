<?php

namespace App\Automotive;

use App\Blocks\BlockStateDefaults;
use App\Blocks\BlockVersionGrants;
use App\Enums\BlockOwnerScope;
use App\Enums\SectionPageRole;
use App\Enums\SiteSectionType;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;

/**
 * Generated template Pages of a vehicle section (ADR-011 §3): one editable Page per role, created
 * pre-filled when the section appears. They render every mark, model or vehicle of the section and
 * are removed only together with it. Shared Header / Footer come from Site Components (ADR-013).
 */
final class SectionPages
{
    public const LISTING_BLOCK = 'vehicle-section';

    /**
     * @var array<string, list<string>>
     */
    private const CONTENT = [
        'index' => [self::LISTING_BLOCK],
        'mark' => [self::LISTING_BLOCK],
        'model' => [self::LISTING_BLOCK],
        'item' => ['vehicle-gallery', 'vehicle-offers', 'vehicle-characteristics', 'vehicle-equipment'],
        'item_stock' => ['vehicle-gallery', 'vehicle-characteristics', 'vehicle-equipment'],
    ];

    public function __construct(
        private BlockStateDefaults $defaults,
        private BlockVersionGrants $grants,
    ) {}

    /**
     * Creates the missing template Pages of the section; existing ones are left as edited.
     */
    public function ensure(SiteSection $section): void
    {
        $existing = $section->pages()->pluck('section_role')
            ->map(fn (SectionPageRole|string $role): string => $role instanceof SectionPageRole ? $role->value : $role)
            ->all();
        $missing = array_values(array_filter($section->type->roles(), fn (SectionPageRole $role): bool => ! in_array($role->value, $existing, true)));

        if ($missing === []) {
            return;
        }

        $site = $section->site()->firstOrFail();

        DB::transaction(function () use ($site, $section, $missing): void {
            $sortOrder = (int) $site->pages()->max('sort_order');

            foreach ($missing as $role) {
                $page = new Page([
                    'title' => $role->label(),
                    'slug' => self::slug($section, $role),
                    'sort_order' => ++$sortOrder,
                ]);
                $page->site()->associate($site);
                $page->site_section_id = $section->id;
                $page->section_role = $role;
                $page->save();

                $this->fill($site, $page, $role, $section->type);
            }
        });
    }

    /**
     * Template Pages are not reachable by slug; the underscore keeps them out of the customer slug
     * namespace (Page slugs are `[a-z0-9-]`).
     */
    public static function slug(SiteSection $section, SectionPageRole $role): string
    {
        return "_{$section->type->value}-{$role->value}";
    }

    private function fill(Site $site, Page $page, SectionPageRole $role, SiteSectionType $type): void
    {
        $sortOrder = 0;
        $versionIds = [];

        $contentKey = $role === SectionPageRole::Item && $type->fromStock()
            ? 'item_stock'
            : $role->value;

        foreach (self::CONTENT[$contentKey] as $slug) {
            $version = BlockDefinition::query()
                ->where('slug', $slug)
                ->where('owner_scope', BlockOwnerScope::Platform->value)
                ->first()
                ?->versions()
                ->latest('id')
                ->first();

            if ($version !== null) {
                $block = new BlockInstance([
                    'sort_order' => $sortOrder++,
                    'state_json' => $this->defaults->fromSchema($version->schema_json),
                ]);
                $block->page()->associate($page);
                $block->block_version_id = $version->id;
                $block->save();
                $versionIds[] = $version->id;
            }
        }

        $this->grants->grant($site, array_unique($versionIds));
    }
}
