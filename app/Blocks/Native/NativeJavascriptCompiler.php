<?php

namespace App\Blocks\Native;

use App\Publishing\Rendering\PageRenderException;

final class NativeJavascriptCompiler
{
    public const MAX_BYTES = 1048576;

    /** @param list<array<string,mixed>> $pages */
    public function compile(array $pages): string
    {
        $mounts = [];
        foreach ($pages as $page) {
            foreach ($page['blocks'] as $block) {
                if (! isset($block['native']) || trim((string) $block['native']['js']) === '') {
                    continue;
                }
                $source = $block['native'];
                $scope = NativeBlockCompiler::scope((string) $block['definition'], (string) $block['version'], $source);
                $key = json_encode($scope, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $mounts[$scope] ??= "{$key}: function mount(root, props, api) {\n\"use strict\";\n{$source['js']}\n}";
            }
        }
        if ($mounts === []) {
            return '';
        }
        $javascript = "const mounts = Object.freeze({\n".implode(",\n", $mounts)."\n});\nexport function mount(scope, root, props, api) { const fn = mounts[scope]; return typeof fn === 'function' ? fn(root, props, api) : undefined; }\n";
        if (strlen($javascript) > self::MAX_BYTES) {
            throw new PageRenderException('Compiled Native JavaScript exceeds the size limit.');
        }

        return $javascript;
    }
}
