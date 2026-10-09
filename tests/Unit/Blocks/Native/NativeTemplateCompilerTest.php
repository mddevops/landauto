<?php

namespace Tests\Unit\Blocks\Native;

use App\Blocks\BlockTemplateParser;
use App\Blocks\Native\Css\CssParser;
use App\Blocks\Native\Css\CssSelectorScoper;
use App\Blocks\Native\Css\CssTokenizer;
use App\Blocks\Native\NativeBlockCompiler;
use App\Blocks\Native\NativeCompileException;
use App\Blocks\Native\NativeCssCompiler;
use App\Blocks\Native\NativeRender;
use App\Blocks\Native\NativeTemplateCompiler;
use App\Blocks\Native\NativeTemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NativeTemplateCompilerTest extends TestCase
{
    private const FIELDS = [
        ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок'],
        ['key' => 'text', 'type' => 'textarea', 'label' => 'Текст'],
        ['key' => 'link', 'type' => 'text', 'label' => 'Ссылка'],
        ['key' => 'show', 'type' => 'boolean', 'label' => 'Показывать'],
        ['key' => 'count', 'type' => 'number', 'label' => 'Количество'],
        ['key' => 'photo', 'type' => 'image', 'label' => 'Фото'],
        ['key' => 'cta', 'type' => 'action', 'label' => 'Кнопка'],
        ['key' => 'button', 'type' => 'group', 'label' => 'Кнопка', 'fields' => [
            ['key' => 'label', 'type' => 'text', 'label' => 'Текст'],
        ]],
        ['key' => 'items', 'type' => 'repeater', 'label' => 'Элементы', 'fields' => [
            ['key' => 'name', 'type' => 'text', 'label' => 'Название'],
            ['key' => 'icon', 'type' => 'image', 'label' => 'Иконка'],
        ]],
    ];

    private NativeBlockCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compiler = new NativeBlockCompiler(
            new NativeTemplateCompiler(new BlockTemplateParser),
            new NativeTemplateRenderer,
            new NativeCssCompiler(new CssTokenizer, new CssParser, new CssSelectorScoper),
        );
    }

    public function test_values_are_escaped_for_text_and_attribute_contexts(): void
    {
        $render = $this->render('<h2 title="{{ title }}">{{ title }}</h2><p>{{ text }}</p>', [
            'title' => '"><img src=x onerror=alert(1)> & \'q\'',
            'text' => '</p><script>alert(1)</script>',
        ]);

        $this->assertSame(
            '<h2 title="&quot;&gt;&lt;img src=x onerror=alert(1)&gt; &amp; &#039;q&#039;">"&gt;&lt;img src=x onerror=alert(1)&gt; &amp; \'q\'</h2>'
            .'<p>&lt;/p&gt;&lt;script&gt;alert(1)&lt;/script&gt;</p>',
            $render->html,
        );
        $this->assertStringNotContainsString('<script', $render->html);
        $this->assertStringNotContainsString('<img', $render->html);
    }

    public function test_sections_groups_repeaters_and_images_follow_the_sandbox_semantics(): void
    {
        $html = '{{#if show}}<p>да</p>{{else}}<p>нет</p>{{/if}}<span>{{ button.label }}</span><b>{{ count }}</b>'
            .'<ul>{{#each items}}<li>{{ name }}{{#if icon}}<img src="{{ icon.url }}" alt="{{ icon.alt }}">{{/if}}</li>{{/each}}</ul>'
            .'{{#if photo}}<img src="{{ photo.url }}" alt="">{{/if}}';

        $render = $this->render($html, [
            'show' => false,
            'count' => 3,
            'button' => ['label' => 'Ок'],
            'items' => [['name' => 'Один', 'icon' => 'asset-1'], ['name' => '<b>Два</b>']],
            'photo' => 'missing-asset',
        ]);

        $this->assertSame(
            '<p>нет</p><span>Ок</span><b>3</b><ul><li>Один<img src="/_landflow/assets/v/asset-1" alt=""></li><li>&lt;b&gt;Два&lt;/b&gt;</li></ul>',
            $render->html,
        );
    }

    public function test_conditional_attribute_values_and_action_keys(): void
    {
        $render = $this->render(
            '<div class="card {{#if show}}card--on{{/if}}"><button type="button" data-landflow-action="cta">Купить</button><a href="{{ link }}">Ещё</a></div>',
            ['show' => true, 'link' => '/contacts'],
        );

        $this->assertSame('<div class="card card--on"><button type="button" data-landflow-action="cta">Купить</button><a href="/contacts">Ещё</a></div>', $render->html);
        $this->assertSame(['cta'], $render->actions);
    }

    public function test_a_less_than_sign_before_a_value_stays_text(): void
    {
        $this->assertSame('<p>1 &lt; 3</p>', $this->render('<p>1 &lt; {{ count }}</p>', ['count' => 3])->html);
    }

    public function test_multiple_instances_of_one_version_render_independently(): void
    {
        $first = $this->render('<h2>{{ title }}</h2>', ['title' => 'Первый']);
        $second = $this->render('<h2>{{ title }}</h2>', ['title' => 'Второй']);

        $this->assertSame('<h2>Первый</h2>', $first->html);
        $this->assertSame('<h2>Второй</h2>', $second->html);
        $this->assertSame($first->scope, $second->scope);
    }

    public function test_safe_navigation_urls_are_kept(): void
    {
        foreach (['/contacts', '#form', '?utm=1', 'https://example.com/a', 'mailto:a@b.ru', 'tel:+79990000000', 'page'] as $url) {
            $this->assertSame('<a href="'.htmlspecialchars($url).'">x</a>', $this->render('<a href="{{ link }}">x</a>', ['link' => $url])->html);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeLinks(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'uppercase' => ['JavaScript:alert(1)'],
            'whitespace' => [" \tjava\nscript:alert(1)"],
            'vbscript' => ['vbscript:msgbox(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'protocol relative' => ['//evil.example/x'],
            'backslash' => ['/\\evil.example'],
        ];
    }

    #[DataProvider('unsafeLinks')]
    public function test_unsafe_link_values_from_state_fail_the_render(string $link): void
    {
        $this->expectException(NativeCompileException::class);

        $this->render('<a href="{{ link }}">x</a>', ['link' => $link]);
    }

    public function test_image_sources_only_come_from_image_fields(): void
    {
        $this->assertRejected('<img src="/_landflow/assets/x/y">', 'Изображение подключается только полем');
        $this->assertRejected('<img src="https://evil.example/x.png">', 'Изображение подключается только полем');
        $this->assertRejected('<img src="x{{ photo.url }}">', 'Изображение подключается только полем');

        // A text field named like an image key is not a trusted asset URL.
        $fields = [['key' => 'fake', 'type' => 'group', 'label' => 'Г', 'fields' => [['key' => 'url', 'type' => 'text', 'label' => 'Адрес']]]];
        $source = ['html' => '<img src="{{ fake.url }}">', 'css' => '', 'js' => '', 'fields' => $fields];

        $this->expectException(NativeCompileException::class);
        $this->compiler->render(NativeBlockCompiler::scope('hero', '1.0.0', $source), $source, ['fake' => ['url' => 'https://evil.example/x.png']], fn (string $id): string => "/_landflow/assets/v/{$id}");
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejectedTemplates(): array
    {
        return [
            'script' => ['<script>alert(1)</script>', 'Тег <script> запрещён'],
            'uppercase script' => ['<SCRIPT>alert(1)</SCRIPT>', 'Тег <script> запрещён'],
            'style' => ['<style>h2{}</style>', 'Тег <style> запрещён'],
            'link' => ['<link rel="stylesheet" href="/x.css">', 'Тег <link> запрещён'],
            'meta' => ['<meta http-equiv="refresh" content="0">', 'Тег <meta> запрещён'],
            'base' => ['<base href="/">', 'Тег <base> запрещён'],
            'iframe' => ['<iframe src="/"></iframe>', 'Тег <iframe> запрещён'],
            'object' => ['<object data="/x"></object>', 'Тег <object> запрещён'],
            'embed' => ['<embed src="/x">', 'Тег <embed> запрещён'],
            'form' => ['<form><button type="button">x</button></form>', 'Тег <form> запрещён'],
            'input' => ['<input name="x">', 'Тег <input> запрещён'],
            'portal' => ['<portal src="/"></portal>', 'Тег <portal> запрещён'],
            'unknown element' => ['<marquee>x</marquee>', 'не поддерживается'],
            'svg script' => ['<svg><script>alert(1)</script></svg>', 'Тег <script> запрещён'],
            'svg foreignObject' => ['<svg><foreignObject><p>x</p></foreignObject></svg>', 'Тег <foreignobject> запрещён'],
            'onclick' => ['<div onclick="alert(1)">x</div>', 'Атрибут onclick запрещён'],
            'uppercase onerror' => ['<p ONERROR="alert(1)">x</p>', 'Атрибут onerror запрещён'],
            'style attribute' => ['<p style="color:red">x</p>', 'Атрибут style запрещён'],
            'srcset' => ['<p srcset="x">x</p>', 'не поддерживается'],
            'reserved data attribute' => ['<div data-landflow-native="x">x</div>', 'зарезервирован'],
            'reserved id' => ['<div id="lf-page-data">x</div>', 'зарезервированы'],
            'javascript link' => ['<a href="javascript:alert(1)">x</a>', 'схема «javascript:»'],
            'encoded javascript link' => ['<a href="jav&#x09;ascript:alert(1)">x</a>', 'схема «javascript:»'],
            'entity javascript link' => ['<a href="&#106;avascript:alert(1)">x</a>', 'схема «javascript:»'],
            'data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'схема «data:»'],
            'svg external use' => ['<svg><use href="https://evil.example/s.svg#a"></use></svg>', 'фрагмент'],
            'svg external paint' => ['<svg><rect fill="url(https://evil.example/p)"></rect></svg>', 'url(#id)'],
            'template as tag name' => ['<{{ title }}>x', 'именем тега'],
            'template as closing tag' => ['<p>x</{{ title }}></p>', 'именем тега'],
            'template in bogus closing tag' => ['<p>x</ {{ title }}></p>', 'именем тега'],
            'template in markup declaration' => ['<p>x<!{{ title }}></p>', 'именем тега'],
            'template as attribute name' => ['<div {{ title }}>x</div>', 'имя атрибута'],
            'template in comment' => ['<!-- {{ title }} --><p>x</p>', 'комментариев'],
            'section across elements' => ['<div>{{#if show}}</div><div>{{/if}}</div>', 'одного элемента'],
            'stray closing tag' => ['</div><p>x</p>', 'закрывающий тег </div>'],
            'escaping the root' => ['<p>x</p></div><script>alert(1)</script>', 'закрывающий тег'],
            'misnested' => ['<p><div>x</div></p>', 'закрывающий тег </p>'],
            'unknown action' => ['<button type="button" data-landflow-action="buy">x</button>', 'не описано в схеме'],
            'dynamic action' => ['<button type="button" data-landflow-action="{{ title }}">x</button>', 'без подстановок'],
            'submit button' => ['<button type="submit">x</button>', 'type="button"'],
            'marker characters' => ["<p>\u{E000}0\u{E001}</p>", 'U+E000'],
            'template syntax' => ['<p>{{#if show}}x</p>', 'Не закрыт блок'],
        ];
    }

    #[DataProvider('rejectedTemplates')]
    public function test_unsafe_or_ambiguous_templates_are_rejected(string $html, string $message): void
    {
        $this->assertRejected($html, $message);
    }

    public function test_error_reports_the_template_line(): void
    {
        try {
            $this->render("<section>\n<p>ok</p>\n<iframe></iframe>\n</section>", []);
            $this->fail('Expected a compile error.');
        } catch (NativeCompileException $exception) {
            $this->assertSame('html', $exception->source);
            $this->assertSame(3, $exception->sourceLine);
        }
    }

    public function test_safe_svg_and_rich_markup_survive(): void
    {
        $html = '<svg viewBox="0 0 10 10" aria-hidden="true"><defs><linearGradient id="g"><stop offset="0" stop-color="#fff"></stop></linearGradient></defs><path d="M0 0L10 10" fill="url(#g)"/><use href="#g"/></svg><table><tbody><tr><td colspan="2">a&amp;b</td></tr></tbody></table>';

        $this->assertSame(
            '<svg viewbox="0 0 10 10" aria-hidden="true"><defs><lineargradient id="g"><stop offset="0" stop-color="#fff"></stop></lineargradient></defs><path d="M0 0L10 10" fill="url(#g)"></path><use href="#g"></use></svg><table><tbody><tr><td colspan="2">a&amp;b</td></tr></tbody></table>',
            $this->render($html, [])->html,
        );
    }

    private function assertRejected(string $html, string $message): void
    {
        try {
            $this->render($html, ['title' => 'x', 'show' => true]);
            $this->fail("Template was accepted: {$html}");
        } catch (NativeCompileException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function render(string $html, array $state): NativeRender
    {
        $source = ['html' => $html, 'css' => '', 'js' => '', 'fields' => self::FIELDS];

        return $this->compiler->render(
            NativeBlockCompiler::scope('hero', '1.0.0', $source),
            $source,
            $state,
            fn (string $id): ?string => $id === 'missing-asset' ? null : "/_landflow/assets/v/{$id}",
        );
    }
}
