<?php

namespace Tests\Unit\Blocks;

use App\Blocks\BlockTemplateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BlockTemplateParserTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private static function fields(): array
    {
        return [
            ['key' => 'title', 'type' => 'text', 'label' => 'Заголовок'],
            ['key' => 'show', 'type' => 'boolean', 'label' => 'Показывать'],
            ['key' => 'count', 'type' => 'number', 'label' => 'Количество'],
            ['key' => 'photo', 'type' => 'image', 'label' => 'Фото'],
            ['key' => 'cta', 'type' => 'action', 'label' => 'Действие'],
            ['key' => 'button', 'type' => 'group', 'label' => 'Кнопка', 'fields' => [
                ['key' => 'label', 'type' => 'text', 'label' => 'Текст'],
            ]],
            ['key' => 'items', 'type' => 'repeater', 'label' => 'Элементы', 'max_items' => 3, 'fields' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Название'],
                ['key' => 'icon', 'type' => 'image', 'label' => 'Иконка'],
            ]],
        ];
    }

    public function test_valid_template_has_no_errors(): void
    {
        $template = <<<'HTML'
            <section>
              {{#if show}}<h2>{{ title }}</h2>{{else}}<h2>Скрыто</h2>{{/if}}
              <img src="{{ photo.url }}" alt="{{ photo.alt }}">
              <button data-landflow-action="cta">{{ button.label }} ({{count}})</button>
              {{#each items}}<p>{{ name }} {{ icon.url }} {{ title }}</p>{{/each}}
            </section>
            HTML;

        $this->assertSame([], (new BlockTemplateParser)->errors($template, self::fields()));
        $this->assertSame([], (new BlockTemplateParser)->errors('<p>Без полей</p>', []));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function invalidTemplates(): array
    {
        return [
            'unclosed if' => ["<p>\n{{#if show}}x", 2, 'Не закрыт блок {{#if'],
            'unclosed each' => ['{{#each items}}', 1, 'Не закрыт блок {{#each'],
            'stray close' => ["a\n\n{{/if}}", 3, 'Лишний {{/if}}'],
            'mismatched close' => ['{{#if show}}{{/each}}{{/if}}', 1, 'Лишний {{/each}}'],
            'else outside if' => ['{{else}}', 1, '{{else}} допустим'],
            'double else' => ['{{#if show}}a{{else}}b{{else}}c{{/if}}', 1, '{{else}} допустим'],
            'unknown construct' => ['{{> partial}}', 1, 'Неизвестная конструкция'],
            'raw output' => ['{{{ title }}}', 1, 'Неизвестная конструкция'],
            'unclosed braces' => ["ok\n{{ title", 2, 'не закрыта'],
            'unknown field' => ['{{ subtitle }}', 1, 'Поле «subtitle» не описано в схеме.'],
            'unknown nested' => ['{{ button.url }}', 1, 'нет вложенного значения «url»'],
            'repeater as value' => ['{{ items }}', 1, 'выводится через {{#each items}}'],
            'group as value' => ['{{ button }}', 1, 'Группу «button» нельзя вывести целиком'],
            'image as value' => ['{{ photo }}', 1, '{{ photo.url }}'],
            'action as value' => ['{{ cta }}', 1, 'data-landflow-action="cta"'],
            'each on text' => ['{{#each title}}{{/each}}', 1, 'работает только с повторителем'],
            'item key outside each' => ['{{ name }}', 1, 'Поле «name» не описано в схеме.'],
            'image subkey' => ['{{ photo.url.x }}', 1, 'нет вложенного значения «x»'],
        ];
    }

    #[DataProvider('invalidTemplates')]
    public function test_invalid_templates_report_line_and_russian_message(string $template, int $line, string $message): void
    {
        $errors = (new BlockTemplateParser)->errors($template, self::fields());
        $match = array_filter($errors, fn (array $error): bool => str_contains($error['message'], $message));

        $this->assertNotEmpty($match, 'Errors: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        $this->assertSame($line, array_values($match)[0]['line']);
    }

    public function test_paths_are_not_checked_without_a_valid_schema(): void
    {
        $parser = new BlockTemplateParser;

        $this->assertSame([], $parser->errors('{{ anything.goes }}{{#each list}}{{ x }}{{/each}}', null));
        $this->assertCount(1, $parser->errors('{{#if x}}', null));
    }
}
