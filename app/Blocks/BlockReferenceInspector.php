<?php

namespace App\Blocks;

use App\Models\BlockInstance;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds stale references in saved Block state (X-018): actions pointing to deleted Pages,
 * deleted or hidden scroll targets, deleted or disabled Popups, Popups whose Form is
 * disabled, and removed assets or vehicles. Saves validate references, but deleting the
 * target later never rewrites existing state, so the Designer warns and Publish blocks.
 * It only reads; it never changes Block state.
 *
 * @phpstan-type Issue array{kind: string, path: string, target: string, severity: 'error'|'warning', message: string}
 */
final class BlockReferenceInspector
{
    /** Reference kind collected by the state validator => issue kind. */
    private const KINDS = ['assets' => 'asset', 'pages' => 'page', 'blocks' => 'block', 'vehicles' => 'vehicle', 'popups' => 'popup'];

    private const MESSAGES = [
        'pages' => 'Действие ведёт на страницу, которой больше нет.',
        'blocks' => 'Действие прокручивает к блоку, которого больше нет на этой странице.',
        'hidden_block' => 'Действие прокручивает к скрытому блоку.',
        'popups' => 'Действие открывает попап, который удалён или выключен.',
        'popup_form' => 'Форма этого попапа выключена: попап откроется без формы.',
        'assets' => 'Изображение удалено из библиотеки сайта.',
        'vehicles' => 'Автомобиль удалён с сайта.',
    ];

    public function __construct(private BlockStateValidator $validator) {}

    /**
     * Issues per Block public ID for one Page. With `$visibleOnly`, hidden Blocks are skipped
     * and a scroll target that is hidden counts as broken, matching what will be published.
     *
     * @param  Collection<int, BlockInstance>|null  $blocks
     * @return array<string, list<Issue>>
     */
    public function inspectPage(Page $page, ?Collection $blocks = null, bool $visibleOnly = false): array
    {
        $blocks ??= $page->blocks()->with('version')->get();
        $blocks->loadMissing('version');
        $sources = $visibleOnly ? $blocks->reject(fn (BlockInstance $block): bool => $block->is_hidden) : $blocks;
        $references = [];

        foreach ($sources as $block) {
            $references[$block->public_id] = $this->validator->references($block->version->schema_json, $block->state_json);
        }

        $resolver = new PageBlockReferences($page);
        $existing = [];

        foreach (array_keys(self::KINDS) as $kind) {
            $ids = [];

            foreach ($references as $refs) {
                foreach ($refs[$kind] as $id) {
                    $ids[$id] = true;
                }
            }

            $ids = array_map('strval', array_keys($ids));
            $existing[$kind] = $ids === [] ? [] : match ($kind) {
                'assets' => $resolver->existingAssets($ids),
                'pages' => $resolver->existingPages($ids),
                'blocks' => $resolver->existingBlocks($ids),
                'vehicles' => $resolver->existingVehicles($ids),
                'popups' => $resolver->existingPopups($ids),
            };
        }

        $hiddenBlocks = $blocks->filter(fn (BlockInstance $block): bool => $block->is_hidden)->pluck('public_id')->all();
        $popupsWithDisabledForm = $existing['popups'] === [] ? [] : Popup::query()
            ->where('site_id', $page->site_id)
            ->whereIn('public_id', $existing['popups'])
            ->whereHas('form', fn ($query) => $query->where('status', false))
            ->pluck('public_id')
            ->all();
        $issues = [];

        foreach ($references as $blockId => $refs) {
            $blockIssues = [];

            foreach ($refs as $kind => $paths) {
                foreach ($paths as $path => $target) {
                    if (! in_array($target, $existing[$kind], true)) {
                        $blockIssues[] = $this->issue(self::KINDS[$kind], $path, $target, 'error', self::MESSAGES[$kind]);
                    } elseif ($kind === 'blocks' && $visibleOnly && in_array($target, $hiddenBlocks, true)) {
                        $blockIssues[] = $this->issue('hidden_block', $path, $target, 'error', self::MESSAGES['hidden_block']);
                    } elseif ($kind === 'popups' && in_array($target, $popupsWithDisabledForm, true)) {
                        $blockIssues[] = $this->issue('popup_form', $path, $target, 'warning', self::MESSAGES['popup_form']);
                    }
                }
            }

            if ($blockIssues !== []) {
                $issues[$blockId] = $blockIssues;
            }
        }

        return $issues;
    }

    /**
     * Issues for every Page of the Site, flattened with their Page and Block public IDs.
     *
     * @return list<array{page: string, block: string, kind: string, path: string, target: string, severity: 'error'|'warning', message: string}>
     */
    public function inspectSite(Site $site, bool $visibleOnly = false): array
    {
        $flat = [];

        foreach ($site->pages()->orderBy('sort_order')->orderBy('id')->get() as $page) {
            foreach ($this->inspectPage($page, null, $visibleOnly) as $blockId => $issues) {
                foreach ($issues as $issue) {
                    $flat[] = ['page' => $page->public_id, 'block' => $blockId, ...$issue];
                }
            }
        }

        return $flat;
    }

    /**
     * @param  'error'|'warning'  $severity
     * @return Issue
     */
    private function issue(string $kind, string $path, string $target, string $severity, string $message): array
    {
        return ['kind' => $kind, 'path' => $path, 'target' => $target, 'severity' => $severity, 'message' => $message];
    }
}
