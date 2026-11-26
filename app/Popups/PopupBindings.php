<?php

namespace App\Popups;

use App\Models\Popup;
use App\Models\Site;

/**
 * Finds and rewrites Site references to a Popup (block actions and the floating launcher).
 *
 * @phpstan-type Binding array{kind: string, label: string, page?: string}
 */
final class PopupBindings
{
    /**
     * @return array<string, list<Binding>>
     */
    public function forSite(Site $site): array
    {
        $ids = $site->popups()->pluck('public_id')->all();
        $index = [];

        foreach ($ids as $id) {
            $index[(string) $id] = [];
        }

        $pages = $site->pages()->with(['blocks.version.definition'])->orderBy('sort_order')->orderBy('id')->get();

        foreach ($pages as $page) {
            foreach ($page->blocks as $block) {
                foreach ($this->popupIds($block->state_json) as $popupId) {
                    if (! isset($index[$popupId])) {
                        continue;
                    }

                    $index[$popupId][] = [
                        'kind' => 'block',
                        'label' => $block->version->definition->name,
                        'page' => $page->title,
                    ];
                }
            }
        }

        foreach ($site->components()->with('version.definition')->get() as $component) {
            foreach ($this->popupIds($component->state_json) as $popupId) {
                if (! isset($index[$popupId])) {
                    continue;
                }

                $index[$popupId][] = [
                    'kind' => 'component',
                    'label' => $component->version->definition->name,
                    'page' => $component->version->definition->component_position?->label() ?? 'Общий блок',
                ];
            }
        }

        $launcher = FloatingLauncher::present($site);
        $launcherPopup = $launcher['popup'] ?? null;

        if (is_string($launcherPopup) && isset($index[$launcherPopup])) {
            $index[$launcherPopup][] = [
                'kind' => 'launcher',
                'label' => 'Плавающая кнопка',
            ];
        }

        return $index;
    }

    /**
     * @return list<Binding>
     */
    public function forPopup(Popup $popup): array
    {
        return $this->forSite($popup->site)[$popup->public_id] ?? [];
    }

    public function reassign(Site $site, string $from, string $to): void
    {
        $pages = $site->pages()->with('blocks')->get();

        foreach ($pages as $page) {
            foreach ($page->blocks as $block) {
                $next = $this->replace($block->state_json, $from, $to);

                if ($next !== $block->state_json) {
                    $block->state_json = $next;
                    $block->save();
                }
            }
        }

        foreach ($site->components()->get() as $component) {
            $next = $this->replace($component->state_json, $from, $to);

            if ($next !== $component->state_json) {
                $component->state_json = $next;
                $component->save();
            }
        }

        $launcher = $site->floating_launcher_json;

        if (is_array($launcher) && ($launcher['popup'] ?? null) === $from) {
            $launcher['popup'] = $to;
            $site->forceFill(['floating_launcher_json' => $launcher])->save();
        }
    }

    /**
     * @return list<string>
     */
    private function popupIds(mixed $state): array
    {
        $ids = [];
        $this->walk($state, function (string $id) use (&$ids): void {
            $ids[$id] = true;
        });

        return array_keys($ids);
    }

    /**
     * @param  array<string, mixed>|null  $state
     * @return array<string, mixed>|null
     */
    private function replace(mixed $state, string $from, string $to): mixed
    {
        if (! is_array($state)) {
            return $state;
        }

        if (($state['type'] ?? null) === 'open_popup' && ($state['popup'] ?? null) === $from) {
            $state['popup'] = $to;
        }

        foreach ($state as $key => $value) {
            if (is_array($value)) {
                $state[$key] = $this->replace($value, $from, $to);
            }
        }

        return $state;
    }

    private function walk(mixed $node, callable $onPopup): void
    {
        if (! is_array($node)) {
            return;
        }

        if (($node['type'] ?? null) === 'open_popup' && is_string($node['popup'] ?? null) && $node['popup'] !== '') {
            $onPopup($node['popup']);
        }

        foreach ($node as $value) {
            $this->walk($value, $onPopup);
        }
    }
}
