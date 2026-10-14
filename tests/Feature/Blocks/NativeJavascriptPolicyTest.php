<?php

namespace Tests\Feature\Blocks;

use App\Blocks\Native\NativeJavascriptPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NativeJavascriptPolicyTest extends TestCase
{
    #[DataProvider('forbidden')]
    public function test_forbidden_javascript_is_rejected(string $source): void
    {
        $this->assertNotSame([], app(NativeJavascriptPolicy::class)->issues($source));
    }

    public static function forbidden(): array
    {
        return array_map(fn (string $source): array => [$source], [
            'eval("x")', 'new Function("x")', 'Function("x")', 'import x from "x"', 'import("x")',
            'export const x = 1', 'fetch("/")', 'new XMLHttpRequest()', 'new WebSocket("x")',
            'new EventSource("x")', 'navigator.sendBeacon("/", "x")', 'new Worker("x")',
            'new SharedWorker("x")', 'document.body', 'window.location', 'globalThis.x',
        ]);
    }

    public function test_local_interaction_and_harmless_text_are_allowed(): void
    {
        $source = <<<'JS'
const text = "fetch window document"; // eval import export
const button = root.querySelector('[data-next]');
const onClick = () => { button?.classList.toggle('active'); };
button?.addEventListener('click', onClick);
const observer = new ResizeObserver(() => requestAnimationFrame(() => {}));
observer.observe(root);
return () => { button?.removeEventListener('click', onClick); observer.disconnect(); };
JS;
        $this->assertSame([], app(NativeJavascriptPolicy::class)->issues($source));
    }
}
