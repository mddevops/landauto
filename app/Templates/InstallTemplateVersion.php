<?php

namespace App\Templates;

use App\Blocks\BlockVersionGrants;
use App\Blocks\SiteComponents;
use App\Enums\BlockActionType;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteComponent;
use App\Models\TemplateVersion;
use LogicException;

/**
 * Copies a published Template Version into independent Site Pages and Block Instances (P9-015).
 * Component Definitions become Site Components once (ADR-013). New public IDs are minted; page
 * and scroll targets are remapped from the Template keys. Later Template changes never sync.
 * Runs inside the caller's transaction; access is checked by callers. Every copied Block Version
 * is granted to the Site (D-122), so later access changes never break it.
 */
final class InstallTemplateVersion
{
    private const REFERENCE_ACTIONS = [BlockActionType::OpenPage, BlockActionType::ScrollTo];

    public function __construct(
        private BlockVersionGrants $grants,
        private SiteComponents $components,
    ) {}

    public function install(Site $site, TemplateVersion $version): void
    {
        $content = $version->content_json ?? throw new LogicException('Template Version has no content to install.');
        $versions = BlockVersion::query()->with('definition')->whereIn('id', TemplateVersion::blockVersionIds($content))->get()->keyBy('id');

        /** @var array<string, string> $pageMap */
        $pageMap = [];
        /** @var list<array{0: Page, 1: list<array{key: string, block_version_id: int, is_hidden: bool, state: array<string, mixed>}>}> $pages */
        $pages = [];

        foreach ($content['pages'] as $index => $source) {
            $page = new Page(['title' => $source['title'], 'slug' => $source['slug'], 'sort_order' => $index]);
            $page->is_home = $source['is_home'];
            $page->site()->associate($site);
            $page->save();
            $pageMap[$source['key']] = $page->public_id;
            $pages[] = [$page, $source['blocks']];
        }

        /** @var list<array{key?: string, block_version_id: int, is_hidden?: bool, state?: array<string, mixed>}> $templateComponents */
        $templateComponents = is_array($content['components'] ?? null) ? array_values(array_filter($content['components'], 'is_array')) : [];

        foreach ($templateComponents as $source) {
            $blockVersion = $versions->get($source['block_version_id']) ?? throw new LogicException('Template Component Version is missing.');
            $this->placeComponent(
                $site,
                $blockVersion,
                $source['key'] ?? null,
                $source['state'] ?? [],
                (bool) ($source['is_hidden'] ?? false),
                $pageMap,
            );
        }

        /** @var array<string, string> $blockMap */
        $blockMap = [];
        /** @var list<array{0: BlockInstance|SiteComponent, 1: array<string, mixed>}> $created */
        $created = [];

        foreach ($pages as [$page, $blocks]) {
            $pageSort = 0;

            foreach ($blocks as $source) {
                $blockVersion = $versions->get($source['block_version_id']) ?? throw new LogicException('Template Block Version is missing.');

                if ($blockVersion->definition->is_component) {
                    $component = $this->placeComponent(
                        $site,
                        $blockVersion,
                        $source['key'],
                        $source['state'],
                        (bool) $source['is_hidden'],
                        $pageMap,
                    );
                    $blockMap[$source['key']] = $component->public_id;
                    $created[] = [$component, $source['state']];

                    continue;
                }

                $instance = new BlockInstance([
                    'sort_order' => $pageSort++,
                    'state_json' => self::remap($source['state'], $pageMap),
                ]);
                $instance->is_hidden = $source['is_hidden'];
                $instance->page()->associate($page);
                $instance->version()->associate($blockVersion);
                $instance->save();
                $blockMap[$source['key']] = $instance->public_id;
                $created[] = [$instance, $source['state']];
            }
        }

        $this->grants->grant($site, $versions->keys()->all());

        // Scroll targets may point at Blocks created later on the same Page, so they are set last.
        foreach ($created as [$instance, $state]) {
            $remapped = self::remap($state, $pageMap + $blockMap);

            if ($remapped !== $instance->state_json) {
                $instance->update(['state_json' => $remapped]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, string>  $pageMap
     */
    private function placeComponent(
        Site $site,
        BlockVersion $version,
        ?string $key,
        array $state,
        bool $hidden,
        array $pageMap,
    ): SiteComponent {
        $existing = $site->components()
            ->whereHas('version', fn ($query) => $query->where('block_definition_id', $version->block_definition_id))
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $component = $this->components->place($site, $version, self::remap($state, $pageMap));

        if ($hidden) {
            SiteComponent::query()->whereKey($component->id)->update(['is_hidden' => true]);
            $component->is_hidden = true;
        }

        return $component;
    }

    /**
     * Rewrites `open_page` / `scroll_to` targets through the map; unknown targets become null.
     *
     * @param  array<array-key, mixed>  $value
     * @param  array<string, string>  $map
     * @return array<array-key, mixed>
     */
    private static function remap(array $value, array $map): array
    {
        $action = is_string($value['type'] ?? null) ? BlockActionType::tryFrom($value['type']) : null;

        if ($action !== null && in_array($action, self::REFERENCE_ACTIONS, true)) {
            $key = $action->targetKey();
            $target = $value[$key] ?? null;
            $value[$key] = is_string($target) ? ($map[$target] ?? null) : null;

            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::remap($item, $map);
            }
        }

        return $value;
    }
}
