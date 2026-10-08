<?php

namespace App\Templates;

use App\Blocks\BlockVersionGrants;
use App\Enums\BlockActionType;
use App\Models\BlockInstance;
use App\Models\BlockVersion;
use App\Models\Page;
use App\Models\Site;
use App\Models\TemplateVersion;
use LogicException;

/**
 * Copies a published Template Version into independent Site Pages and Block Instances (P9-015).
 * New public IDs are minted; page and scroll targets are remapped from the Template keys. Later
 * Template changes never sync. Runs inside the caller's transaction; access is checked by callers.
 * Every copied Block Version is granted to the Site (D-122), so later access changes never break it.
 */
final class InstallTemplateVersion
{
    private const REFERENCE_ACTIONS = [BlockActionType::OpenPage, BlockActionType::ScrollTo];

    public function __construct(private BlockVersionGrants $grants) {}

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

        /** @var array<string, string> $blockMap */
        $blockMap = [];
        /** @var list<array{0: BlockInstance, 1: array<string, mixed>}> $created */
        $created = [];

        foreach ($pages as [$page, $blocks]) {
            foreach ($blocks as $index => $source) {
                $blockVersion = $versions->get($source['block_version_id']) ?? throw new LogicException('Template Block Version is missing.');
                $instance = new BlockInstance(['sort_order' => $index, 'state_json' => self::remap($source['state'], $pageMap)]);
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
