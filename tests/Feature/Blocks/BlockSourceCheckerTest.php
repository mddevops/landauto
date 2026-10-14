<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockSourceChecker;
use App\Blocks\BlockStudio;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlockSourceCheckerTest extends TestCase
{
    private const SCHEMA = '{"fields":[{"key":"title","type":"text","label":"Заголовок"},{"key":"cta","type":"action","label":"Кнопка"}]}';

    /**
     * @param  array<string, string>  $overrides
     * @return list<array{source: string, line: int|null, path: string|null, message: string}>
     */
    private function check(array $overrides): array
    {
        return app(BlockSourceChecker::class)->check([
            'html' => '<h2>{{ title }}</h2>',
            'css' => '.block { color: red; }',
            'js' => '',
            'schema' => self::SCHEMA,
            ...$overrides,
        ]);
    }

    public function test_clean_sources_pass_every_check(): void
    {
        $html = <<<'HTML'
            <section style="background: url(data:image/png;base64,AAAA)">
              <h2>{{ title }}</h2>
              <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M0 0h1"/></svg>
              <button data-landflow-action="cta">Подробнее</button>
              <a href="#contacts" data-landflow-action='cta'>Связаться</a>
            </section>
            HTML;

        $this->assertSame([], $this->check(['html' => $html, 'js' => 'root.classList.add("ready");']));
    }

    /**
     * @return array<string, array{array<string, string>, string, int|null, string|null, string}>
     */
    public static function failingSources(): array
    {
        return [
            'schema empty' => [['schema' => '  '], 'schema', null, 'schema', 'Схема пуста'],
            'schema invalid json' => [['schema' => '{"fields": ['], 'schema', null, 'schema', 'некорректный JSON'],
            'schema canonical error' => [['schema' => '{"fields":[{"key":"Title","type":"text","label":"X"}]}'], 'schema', null, 'fields.0.key', ''],
            'template syntax' => [['html' => "<h2>\n{{#if title}}"], 'html', 2, null, 'Не закрыт блок {{#if'],
            'template path' => [['html' => '{{ subtitle }}'], 'html', 1, null, 'Поле «subtitle» не описано в схеме.'],
            'script element' => [['html' => "<p>\n<SCRIPT>alert(1)</SCRIPT>"], 'html', 2, null, 'Тег <script> запрещён'],
            'style element' => [['html' => '<style>p{}</style>'], 'html', 1, null, 'Тег <style> запрещён'],
            'link element' => [['html' => '<link rel="stylesheet" href="a.css">'], 'html', 1, null, 'Тег <link> запрещён'],
            'meta element' => [['html' => '<meta http-equiv="refresh" content="0">'], 'html', 1, null, 'Тег <meta> запрещён'],
            'base element' => [['html' => '<base href="/">'], 'html', 1, null, 'Тег <base> запрещён'],
            'iframe element' => [['html' => '<iframe src="data:text/html,x"></iframe>'], 'html', 1, null, 'Тег <iframe> запрещён'],
            'frame element' => [['html' => '<frame>'], 'html', 1, null, 'Тег <frame> запрещён'],
            'object element' => [['html' => '<object data="data:x"></object>'], 'html', 1, null, 'Тег <object> запрещён'],
            'embed element' => [['html' => '<embed/>'], 'html', 1, null, 'Тег <embed> запрещён'],
            'form element' => [['html' => '<form>'], 'html', 1, null, 'Тег <form> запрещён'],
            'portal element' => [['html' => '<portal>'], 'html', 1, null, 'Тег <portal> запрещён'],
            'unknown action key' => [['html' => "<p></p>\n<button data-landflow-action=\"buy\">"], 'html', 2, null, 'Действие «buy» не описано в схеме'],
            'templated action key' => [['html' => '<button data-landflow-action="{{ title }}">'], 'html', 1, null, 'без подстановок'],
            'empty action key' => [['html' => '<button data-landflow-action="">'], 'html', 1, null, 'без подстановок'],
            'action on non action field' => [['html' => '<button data-landflow-action=title>'], 'html', 1, null, 'Действие «title» не описано'],
            'html external url' => [['html' => '<img src="https://cdn.example.com/a.png">'], 'html', 1, null, 'Внешний адрес «https://cdn.example.com/a.png» запрещён в index.html'],
            'html protocol relative' => [['html' => '<img src="//cdn.example.com/a.png">'], 'html', 1, null, 'Адрес, начинающийся с //, запрещён в index.html'],
            'html style url' => [['html' => '<div style="background: url(//x.test/a.png)"></div>'], 'html', 1, null, 'начинающийся с //'],
            'css external url' => [['css' => "a {}\nb { background: url('http://x.test/a.png'); }"], 'css', 2, null, 'запрещён в styles.css'],
            'css protocol relative' => [['css' => 'b { background: url( "//x.test/a.png"); }'], 'css', 1, null, 'начинающийся с //'],
            'css import' => [['css' => "@IMPORT 'theme.css';"], 'css', 1, null, '@import запрещён в styles.css'],
            'html too large' => [['html' => str_repeat('a', BlockStudio::SOURCE_MAX_BYTES + 1)], 'html', null, null, 'index.html больше 64 КБ'],
            'js too large' => [['js' => str_repeat('я', BlockStudio::SOURCE_MAX_BYTES / 2 + 1)], 'js', null, null, 'script.js больше 64 КБ'],
            'invalid utf8' => [['css' => "a { content: '\xC3\x28'; }"], 'css', null, null, 'кодировке UTF-8'],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('failingSources')]
    public function test_each_rule_reports_source_location_and_russian_message(array $overrides, string $source, ?int $line, ?string $path, string $message): void
    {
        $issues = $this->check($overrides);
        $match = array_values(array_filter(
            $issues,
            fn (array $issue): bool => $issue['source'] === $source && $issue['line'] === $line && $issue['path'] === $path && str_contains($issue['message'], $message),
        ));

        $this->assertNotEmpty($match, 'Issues: '.json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function test_action_keys_are_not_checked_against_an_invalid_schema_and_issues_are_ordered_by_file(): void
    {
        $issues = $this->check([
            'html' => "<button data-landflow-action=\"buy\">\n<script></script>",
            'css' => '@import "x.css";',
            'schema' => '{',
        ]);

        $this->assertSame(
            [['html', 2], ['css', 1], ['schema', null]],
            array_map(fn (array $issue): array => [$issue['source'], $issue['line']], $issues),
        );
    }
}
