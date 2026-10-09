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

    public function test_sandboxed_blocks_render_only_an_empty_sandboxed_frame_on_the_server(): void
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
            'blocks' => [['public_id' => '01JCCCCCCCCCCCCCCCCCCCCCCC', 'slug' => 'promo-card', 'state' => ['title' => 'Акция'], 'sandbox' => [
                'name' => 'Промо-карточка',
                'html' => '<h2 class="promo">{{ title }}</h2>',
                'css' => '.promo { color: red; }',
                'js' => 'landflow.resize();',
                'fields' => [['key' => 'title', 'type' => 'text', 'label' => 'Заголовок']],
            ]]],
            'assets' => [],
            'vehicles' => [],
            'popups' => [],
            'form_action' => '/_landflow/forms/01JAAAAAAAAAAAAAAAAAAAAAAA',
        ]]);

        $page = $html['01JBBBBBBBBBBBBBBBBBBBBBBB'];
        $this->assertMatchesRegularExpression('/<iframe[^>]*title="Промо-карточка"[^>]*sandbox="allow-scripts"/u', $page);
        $this->assertStringNotContainsString('srcdoc', strtolower($page));
        foreach (['class="promo"', '.promo', 'landflow.resize', 'Акция'] as $source) {
            $this->assertStringNotContainsString($source, $page, 'Block code and data never enter the host document.');
        }
    }

    public function test_native_blocks_render_compiled_html_inside_their_controlled_root(): void
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
            'blocks' => [['public_id' => '01JCCCCCCCCCCCCCCCCCCCCCCC', 'slug' => 'native-hero', 'state' => ['title' => 'Акция'], 'native' => [
                'scope' => 'native-hero--1-0-0--abcdef0123',
                'html' => '<section class="hero"><h2>Акция &amp; скидка</h2><button type="button" data-landflow-action="cta">Заявка</button></section>',
                'actions' => ['cta'],
            ]]],
            'assets' => [],
            'vehicles' => [],
            'popups' => [],
            'form_action' => '/_landflow/forms/01JAAAAAAAAAAAAAAAAAAAAAAA',
        ]]);

        $this->assertStringContainsString(
            '<div id="block-01JCCCCCCCCCCCCCCCCCCCCCCC"><div data-landflow-native="native-hero--1-0-0--abcdef0123" data-landflow-block="native-hero" data-landflow-instance="01JCCCCCCCCCCCCCCCCCCCCCCC"><section class="hero"><h2>Акция &amp; скидка</h2><button type="button" data-landflow-action="cta">Заявка</button></section></div></div>',
            $html['01JBBBBBBBBBBBBBBBBBBBBBBB'],
        );
        $this->assertStringNotContainsString('<iframe', $html['01JBBBBBBBBBBBBBBBBBBBBBBB']);
    }

    public function test_missing_bundle_fails_safely(): void
    {
        config(['publishing.renderer' => base_path('bootstrap/ssr/missing.js')]);

        $this->expectException(PageRenderException::class);

        (new NodePageRenderer)->render([]);
    }
}
