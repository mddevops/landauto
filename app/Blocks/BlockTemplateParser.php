<?php

namespace App\Blocks;

use App\Enums\BlockFieldType;

/**
 * Validates the logic-less Landflow template of a sandboxed Block (ADR-008 §3):
 * `{{ path }}`, `{{#if path}} … {{else}} … {{/if}}` and `{{#each path}} … {{/each}}`.
 * Rendering happens only inside the sandbox; this parser reports syntax errors and, when the
 * canonical schema is valid, paths that the schema does not declare.
 */
final class BlockTemplateParser
{
    private const PATH = '[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*';

    /** Image values reach the template as `{url, alt}`. */
    private const IMAGE_KEYS = ['url', 'alt'];

    /** @var list<array{line: int, message: string}> */
    private array $errors = [];

    /**
     * @param  list<array<string, mixed>>|null  $fields  top-level schema fields; null skips path checks
     * @return list<array{line: int, message: string}>
     */
    public function errors(string $template, ?array $fields): array
    {
        $this->errors = [];
        /** @var list<array{kind: string, line: int, else: bool}> $sections */
        $sections = [];
        /** @var list<list<array<string, mixed>>> $scopes */
        $scopes = $fields === null ? [] : [$fields];

        preg_match_all('/\{\{(.*?)\}\}/s', $template, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $end = 0;

        foreach ($matches as $match) {
            $line = $this->line($template, $match[0][1]);
            $inner = trim($match[1][0]);
            $end = $match[0][1] + strlen($match[0][0]);

            if (preg_match('/^#(if|each)\s+('.self::PATH.')$/', $inner, $open) === 1) {
                $field = $fields === null ? null : $this->resolve($open[2], $scopes, $open[1], $line);
                $sections[] = ['kind' => $open[1], 'line' => $line, 'else' => false];

                if ($fields !== null) {
                    $scopes[] = $open[1] === 'each' && $field !== null ? $this->nested($field) : [];
                }
            } elseif ($inner === 'else') {
                $top = array_key_last($sections);

                if ($top === null || $sections[$top]['kind'] !== 'if' || $sections[$top]['else']) {
                    $this->error($line, '{{else}} допустим только один раз внутри {{#if …}}.');
                } else {
                    $sections[$top]['else'] = true;
                }
            } elseif (preg_match('/^\/(if|each)$/', $inner, $close) === 1) {
                $top = array_pop($sections);

                if ($top === null || $top['kind'] !== $close[1]) {
                    $this->error($line, "Лишний {{/{$close[1]}}} без открывающего {{#{$close[1]} …}}.");

                    if ($top !== null) {
                        $sections[] = $top;
                    }
                } elseif ($fields !== null) {
                    array_pop($scopes);
                }
            } elseif (preg_match('/^'.self::PATH.'$/', $inner) === 1) {
                if ($fields !== null) {
                    $this->resolve($inner, $scopes, 'value', $line);
                }
            } else {
                $this->error($line, 'Неизвестная конструкция {{'.mb_substr($match[1][0], 0, 40).'}}. Используйте {{ поле }}, {{#if поле}}, {{#each поле}}.');
            }
        }

        $unclosed = strpos($template, '{{', $end);

        if ($unclosed !== false) {
            $this->error($this->line($template, $unclosed), 'Конструкция {{ не закрыта символами }}.');
        }

        foreach ($sections as $section) {
            $this->error($section['line'], "Не закрыт блок {{#{$section['kind']} …}}: добавьте {{/{$section['kind']}}}.");
        }

        usort($this->errors, fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return $this->errors;
    }

    /**
     * Resolves a dot path through the innermost-first scopes and checks how it is used.
     *
     * @param  list<list<array<string, mixed>>>  $scopes
     * @return array<string, mixed>|null the resolved field
     */
    private function resolve(string $path, array $scopes, string $usage, int $line): ?array
    {
        $segments = explode('.', $path);
        $field = null;

        foreach (array_reverse($scopes) as $scope) {
            $field = $this->find($scope, $segments[0]);

            if ($field !== null) {
                break;
            }
        }

        if ($field === null) {
            $this->error($line, "Поле «{$segments[0]}» не описано в схеме.");

            return null;
        }

        $imageKey = null;

        foreach (array_slice($segments, 1) as $segment) {
            $type = BlockFieldType::tryFrom((string) $field['type']);

            if ($type === BlockFieldType::Image && $imageKey === null && in_array($segment, self::IMAGE_KEYS, true)) {
                $imageKey = $segment;

                continue;
            }

            $child = $type === BlockFieldType::Group ? $this->find($this->nested($field), $segment) : null;

            if ($imageKey !== null || $child === null) {
                $this->error($line, "В поле «{$field['key']}» нет вложенного значения «{$segment}».");

                return null;
            }

            $field = $child;
        }

        $type = BlockFieldType::tryFrom((string) $field['type']);
        $message = match (true) {
            $type === BlockFieldType::Action => "Действие «{$path}» нельзя вывести в шаблоне: подключите его атрибутом data-landflow-action=\"{$field['key']}\".",
            $type === BlockFieldType::Vehicle => 'Поле «Автомобиль» пока не поддерживается в шаблонах студии.',
            $usage === 'each' => $type === BlockFieldType::Repeater ? null : "{{#each}} работает только с повторителем, а «{$path}» им не является.",
            $usage === 'if' => null,
            $type === BlockFieldType::Repeater => "Повторитель «{$path}» выводится через {{#each {$path}}} … {{/each}}.",
            $type === BlockFieldType::Group => "Группу «{$path}» нельзя вывести целиком: укажите вложенное поле, например {{ {$path}.ключ }}.",
            $type === BlockFieldType::Image && $imageKey === null => "Выведите адрес изображения {{ {$path}.url }} или подпись {{ {$path}.alt }}.",
            default => null,
        };

        if ($message !== null) {
            $this->error($line, $message);
        }

        return $field;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>|null
     */
    private function find(array $fields, string $key): ?array
    {
        foreach ($fields as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<array<string, mixed>>
     */
    private function nested(array $field): array
    {
        /** @var list<array<string, mixed>> $fields */
        $fields = is_array($field['fields'] ?? null) ? $field['fields'] : [];

        return $fields;
    }

    private function line(string $template, int $offset): int
    {
        return substr_count($template, "\n", 0, $offset) + 1;
    }

    private function error(int $line, string $message): void
    {
        $this->errors[] = ['line' => $line, 'message' => $message];
    }
}
