<?php

namespace Tests\Unit\Blocks\Native;

use App\Blocks\Native\Css\CssParser;
use App\Blocks\Native\Css\CssSelectorScoper;
use App\Blocks\Native\Css\CssTokenizer;
use App\Blocks\Native\NativeCompileException;
use App\Blocks\Native\NativeCssCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NativeCssCompilerTest extends TestCase
{
    private const SCOPE = '[data-landflow-native="hero--1-0-0--abc"]';

    private function compile(string $css, string $scope = self::SCOPE, string $prefix = 'lf-abc-'): string
    {
        return (new NativeCssCompiler(new CssTokenizer, new CssParser, new CssSelectorScoper))->compile($css, $scope, $prefix);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function selectors(): array
    {
        $s = self::SCOPE;

        return [
            'class' => ['.card{color:red}', "{$s} .card{color:red}"],
            'type' => ['h2{color:red}', "{$s} h2{color:red}"],
            'universal' => ['*{box-sizing:border-box}', "{$s} *{box-sizing:border-box}"],
            'root' => [':root{--accent:red}', "{$s}{--accent:red}"],
            'html' => ['html{font-size:16px}', "{$s}{font-size:16px}"],
            'body' => ['body{margin:0}', "{$s}{margin:0}"],
            'body descendant' => ['body .card{margin:0}', "{$s} .card{margin:0}"],
            'html body chain' => ['html body > .card{margin:0}', "{$s} > .card{margin:0}"],
            'root with class' => [':root.dark .card{color:#fff}', "{$s}.dark .card{color:#fff}"],
            'attribute' => ['[data-state="on"] a[href^="/"]{color:red}', "{$s} [data-state=\"on\"] a[href^=\"/\"]{color:red}"],
            'pseudo classes' => ['.card:hover,.card:nth-child(2n+1):not(.x){color:red}', "{$s} .card:hover,{$s} .card:nth-child(2n+1):not(.x){color:red}"],
            'pseudo element' => ['.card::before{content:"«»"}', "{$s} .card::before{content:\"«»\"}"],
            'combinators' => ['.a>.b + .c ~ .d .e{color:red}', "{$s} .a > .b + .c ~ .d .e{color:red}"],
            'selector list' => ['h1, h2 ,h3{margin:0}', "{$s} h1,{$s} h2,{$s} h3{margin:0}"],
            'comment between compounds' => ['.a/**/.b{color:red}', "{$s} .a/**/.b{color:red}"],
            'top-level html comment tokens are ignored' => ['<!-- .a{color:red} -->', "{$s} .a{color:red}"],
        ];
    }

    #[DataProvider('selectors')]
    public function test_selectors_are_scoped_to_the_version_root(string $css, string $expected): void
    {
        $this->assertSame($expected, $this->compile($css));
    }

    public function test_media_supports_keyframes_and_animation_references(): void
    {
        $css = "@media (max-width: 600px) { .card { padding: 0 } @supports (display: grid) { .grid { display: grid } } }\n"
            ."@keyframes fade { from { opacity: 0 } 50% { opacity: .5 } to { opacity: 1 } }\n"
            .'.card { animation: fade 1.5s ease-in-out 200ms infinite alternate; animation-name: fade, other }';

        $this->assertSame(
            '@media (max-width: 600px){'.self::SCOPE.' .card{padding:0}@supports (display: grid){'.self::SCOPE." .grid{display:grid}}}\n"
            ."@keyframes lf-abc-fade{from{opacity:0}50%{opacity:.5}to{opacity:1}}\n"
            .self::SCOPE.' .card{animation:lf-abc-fade 1.5s ease-in-out 200ms infinite alternate;animation-name:lf-abc-fade, other}',
            $this->compile($css),
        );
    }

    public function test_colliding_rules_of_two_blocks_stay_isolated(): void
    {
        $css = ':root { --accent: red } .card { color: var(--accent); animation: fade 1s } h2 { margin: 0 } * { box-sizing: border-box } @keyframes fade { to { opacity: 1 } }';
        $first = $this->compile($css, '[data-landflow-native="a--1-0-0--1111111111"]', 'lf-1111111111-');
        $second = $this->compile($css, '[data-landflow-native="b--1-0-0--2222222222"]', 'lf-2222222222-');

        foreach (['[data-landflow-native="a--1-0-0--1111111111"]{--accent:red}', '[data-landflow-native="a--1-0-0--1111111111"] .card{', '[data-landflow-native="a--1-0-0--1111111111"] h2{', '[data-landflow-native="a--1-0-0--1111111111"] *{', '@keyframes lf-1111111111-fade', 'animation:lf-1111111111-fade 1s'] as $part) {
            $this->assertStringContainsString($part, $first);
        }

        $this->assertStringContainsString('@keyframes lf-2222222222-fade', $second);
        $this->assertStringNotContainsString('lf-1111111111', $second);
        $this->assertStringNotContainsString('@keyframes fade', $first.$second);
        $this->assertStringNotContainsString(':root', $first.$second);
    }

    public function test_output_is_deterministic(): void
    {
        $css = '.a{color:red} @media print{.b{display:none}}';

        $this->assertSame($this->compile($css), $this->compile($css));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejected(): array
    {
        return [
            'import' => ['@import "theme.css";', '@import запрещён'],
            'import url' => ['@import url(https://evil.example/x.css);', '@import запрещён'],
            'external url' => ['.a{background:url(https://evil.example/x.png)}', 'url() запрещён'],
            'quoted url' => ['.a{background:url("/x.png")}', 'url() запрещена'],
            'protocol relative url' => ['.a{background:url(//evil.example/x.png)}', 'url() запрещён'],
            'data url' => ['.a{background:url(data:image/png;base64,AAAA)}', 'url() запрещён'],
            'escaped url' => ['.a{background:u\\72l(x.png)}', 'url() запрещён'],
            'uppercase url' => ['.a{background:URL(x.png)}', 'url() запрещён'],
            'image-set' => ['.a{background:image-set("x.png" 1x)}', 'image-set() запрещена'],
            'cursor url' => ['.a{cursor:url(x.cur),auto}', 'url() запрещён'],
            'custom property url' => [':root{--bg:url(x.png)}', 'url() запрещён'],
            'expression' => ['.a{width:expression(alert(1))}', 'expression() запрещена'],
            'behavior' => ['.a{behavior:x}', 'запрещено'],
            'font-face' => ['@font-face{font-family:x;src:local(x)}', '@font-face запрещён'],
            'unknown at-rule' => ['@layer base{.a{color:red}}', 'не поддерживается'],
            'container' => ['@container (min-width: 1px){.a{color:red}}', 'не поддерживается'],
            'nested rule' => ['.a{color:red;.b{color:blue}}', 'Вложенные правила'],
            'nesting selector' => ['& .a{color:red}', 'Вложенный селектор &'],
            'sibling after root' => [':root + .x{color:red}', 'выходят за пределы блока'],
            'sibling after body' => ['body ~ .x{color:red}', 'выходят за пределы блока'],
            'leading combinator' => ['> .a{color:red}', 'комбинатора'],
            'unclosed block' => ['.a{color:red', 'Не закрыта скобка'],
            'stray brace' => ['.a{color:red}}', 'Лишняя закрывающая скобка'],
            'unclosed comment' => ['.a{color:red} /* ', 'Комментарий'],
            'unclosed string' => ['.a::before{content:"x}', 'Строка'],
            'html comment inside value' => ['.a{color:<!--red}', 'HTML-комментарии'],
            'unsupported pseudo function' => [':host(.a){color:red}', 'host()'],
            'escaped keyframes name' => ['@keyframes f\\61 de{to{opacity:1}}', 'идентификатором'],
            'bad keyframe selector' => ['@keyframes f{.a{opacity:1}}', 'Кадры @keyframes'],
        ];
    }

    #[DataProvider('rejected')]
    public function test_unsafe_or_unsupported_css_is_rejected(string $css, string $message): void
    {
        try {
            $this->compile($css);
            $this->fail("CSS was accepted: {$css}");
        } catch (NativeCompileException $exception) {
            $this->assertSame('css', $exception->source);
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    public function test_errors_report_the_stylesheet_line(): void
    {
        try {
            $this->compile(".a { color: red }\n\n.b { background: url(x.png) }");
            $this->fail('Expected a compile error.');
        } catch (NativeCompileException $exception) {
            $this->assertSame(3, $exception->sourceLine);
        }
    }
}
