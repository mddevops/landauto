<?php

namespace Tests\Feature\Publishing;

use App\Publishing\Rendering\NodePageRenderer;
use App\Publishing\Rendering\PageRenderException;
use Tests\TestCase;

/**
 * Exercises the real compiled renderer. It exists only after `npm run build`, so the test is
 * skipped on a fresh checkout; the browser E2E suite always covers it.
 */
class NodePageRendererTest extends TestCase
{
    public function test_compiled_renderer_produces_server_html_for_official_blocks(): void
    {
        if (! is_file((string) config('publishing.renderer'))) {
            $this->markTestSkipped('The publish renderer bundle has not been built.');
        }

        $html = (new NodePageRenderer)->render([[
            'version' => '01JAAAAAAAAAAAAAAAAAAAAAAA',
            'site' => ['name' => 'Дилер'],
            'page' => ['public_id' => '01JBBBBBBBBBBBBBBBBBBBBBBB', 'title' => 'Главная'],
            'pages' => [['public_id' => '01JBBBBBBBBBBBBBBBBBBBBBBB', 'path' => '/']],
            'design' => ['primary_color' => '#171717', 'secondary_color' => '#525252', 'font_family' => 'sans', 'radius' => 'medium', 'container' => 'default', 'button_style' => 'solid'],
            'blocks' => [['public_id' => '01JCCCCCCCCCCCCCCCCCCCCCCC', 'slug' => 'hero', 'state' => ['title' => 'Цена <2 100 000 ₽>']]],
            'assets' => [],
            'vehicles' => [],
            'popups' => [],
            'form_action' => '/_landflow/forms/01JAAAAAAAAAAAAAAAAAAAAAAA',
        ]]);

        $page = $html['01JBBBBBBBBBBBBBBBBBBBBBBB'];
        $this->assertStringContainsString('Цена &lt;2 100 000 ₽&gt;', $page);
        $this->assertStringContainsString('id="block-01JCCCCCCCCCCCCCCCCCCCCCCC"', $page);
        $this->assertStringNotContainsString('Landflow', $page, 'Branding is added at request time, never stored.');
    }

    public function test_missing_bundle_fails_safely(): void
    {
        config(['publishing.renderer' => base_path('bootstrap/ssr/missing.js')]);

        $this->expectException(PageRenderException::class);

        (new NodePageRenderer)->render([]);
    }
}
