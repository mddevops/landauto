<?php

namespace App\Publishing\Rendering;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use JsonException;

/**
 * Runs the compiled React renderer (`bootstrap/ssr/render-server.js`, built once per deployment)
 * as a child process: argument array without a shell, payloads on STDIN, bounded runtime.
 */
final class NodePageRenderer implements PageRenderer
{
    public function render(array $payloads): array
    {
        $renderer = (string) config('publishing.renderer');

        if (! is_file($renderer)) {
            throw new PageRenderException('The publish renderer bundle is missing; run the frontend build.');
        }

        try {
            $input = json_encode(['pages' => $payloads], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new PageRenderException('The publish payload could not be encoded.');
        }

        try {
            $result = Process::timeout((int) config('publishing.render_timeout'))
                ->input($input)
                ->run([(string) config('publishing.node_binary'), $renderer]);
        } catch (ProcessTimedOutException) {
            throw new PageRenderException('The publish renderer timed out.');
        }

        if (! $result->successful()) {
            throw new PageRenderException('The publish renderer failed: '.Str::limit(trim($result->errorOutput()), 300));
        }

        try {
            $output = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PageRenderException('The publish renderer returned malformed output.');
        }

        $pages = is_array($output) && is_array($output['pages'] ?? null) ? $output['pages'] : [];
        $html = [];

        foreach ($pages as $page) {
            if (is_array($page) && is_string($page['page'] ?? null) && is_string($page['html'] ?? null)) {
                $html[$page['page']] = $page['html'];
            }
        }

        return $html;
    }
}
