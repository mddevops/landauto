<?php

namespace App\Automotive;

use App\Blocks\BlockStateDefaults;
use App\Blocks\BlockVersionGrants;
use App\Enums\BlockOwnerScope;
use App\Enums\SectionPageRole;
use App\Models\BlockDefinition;
use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;

/**
 * Generated template Pages of a vehicle section (ADR-011 §3): one editable Page per role, created
 * pre-filled when the section appears. They render every mark, model or vehicle of the section and
 * are removed only together with it.
 */
final class SectionPages
{
    public const LISTING_BLOCK = 'vehicle-section';

    /** Header and footer of the home Page are copied so a new template Page looks like the Site. */
    private const SHARED_BLOCKS = ['header', 'footer'];

    /**
     * @var array<string, list<string>>
     */
    private const CONTENT = [
        'index' => [self::LISTING_BLOCK],
        'mark' => [self::LISTING_BLOCK],
        'item' => ['vehicle-gallery', 'vehicle-offers', 'vehicle-characteristics', 'vehicle-equipment'],
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
        $missing = array_values(array_filter(SectionPageRole::cases(), fn (SectionPageRole $role): bool => ! in_array($role->value, $existing, true)));

        if ($missing === []) {
            return;
        }

        $site = $section->site()->firstOrFail();
        $shared = $this->sharedBlocks($site);

        DB::transaction(function () use ($site, $section, $missing, $shared): void {
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

                $this->fill($site, $page, $role, $shared);
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

    /**
     * @param  array{header: BlockInstance|null, footer: BlockInstance|null}  $shared
     */
    private function fill(Site $site, Page $page, SectionPageRole $role, array $shared): void
    {
        $sortOrder = 0;
        $versionIds = [];

        $place = function (int $versionId, array $state) use ($page, &$sortOrder, &$versionIds): void {
            $block = new BlockInstance(['sort_order' => $sortOrder++, 'state_json' => $state]);
            $block->page()->associate($page);
            $block->block_version_id = $versionId;
            $block->save();
            $versionIds[] = $versionId;
        };

        if ($shared['header'] !== null) {
            $place($shared['header']->block_version_id, $shared['header']->state_json);
        }

        foreach (self::CONTENT[$role->value] as $slug) {
            $version = BlockDefinition::query()
                ->where('slug', $slug)
                ->where('owner_scope', BlockOwnerScope::Platform->value)
                ->first()
                ?->versions()
                ->latest('id')
                ->first();

            if ($version !== null) {
                $place($version->id, $this->defaults->fromSchema($version->schema_json));
            }
        }

        if ($shared['footer'] !== null) {
            $place($shared['footer']->block_version_id, $shared['footer']->state_json);
        }

        $this->grants->grant($site, array_unique($versionIds));
    }

    /**
     * @return array{header: BlockInstance|null, footer: BlockInstance|null}
     */
    private function sharedBlocks(Site $site): array
    {
        $home = $site->pages()->where('is_home', true)->first();
        $blocks = $home?->blocks()->with('version.definition')->get() ?? collect();
        $shared = ['header' => null, 'footer' => null];

        foreach (self::SHARED_BLOCKS as $slug) {
            $shared[$slug] = $blocks->first(fn (BlockInstance $block): bool => $block->version->definition->slug === $slug
                && $block->version->definition->isPlatformOwned());
        }

        return $shared;
    }
}
