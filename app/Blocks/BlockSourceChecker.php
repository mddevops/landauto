<?php

namespace App\Blocks;

use App\Enums\BlockFieldType;
use JsonException;

/**
 * Deterministic checks of authored Block sources (ADR-008 §7, D-120). They replace manual review:
 * a Draft with any issue cannot be published, a Draft without issues publishes immediately.
 * Every issue names its source and, where possible, a template line or schema path.
 */
final class BlockSourceChecker
{
    /** Source key => file name shown to the author. */
    public const FILES = ['html' => 'index.html', 'css' => 'styles.css', 'js' => 'script.js', 'schema' => 'schema.json'];

    private const FORBIDDEN_ELEMENTS = ['script', 'style', 'link', 'meta', 'base', 'iframe', 'frame', 'object', 'embed', 'form', 'portal'];

    public function __construct(
        private BlockSchemaValidator $schemas,
        private BlockTemplateParser $templates,
    ) {}

    /**
     * @param  array{html: string, css: string, js: string, schema: string}  $sources
     * @return list<array{source: string, line: int|null, path: string|null, message: string}>
     */
    public function check(array $sources): array
    {
        $issues = [];

        foreach (self::FILES as $source => $file) {
            if (! mb_check_encoding($sources[$source], 'UTF-8')) {
                $issues[] = self::issue($source, null, null, "{$file} содержит недопустимые символы: сохраните файл в кодировке UTF-8.");
            } elseif (strlen($sources[$source]) > BlockStudio::SOURCE_MAX_BYTES) {
                $issues[] = self::issue($source, null, null, "{$file} больше 64 КБ. Сократите файл.");
            }
        }

        if ($issues !== []) {
            return $issues;
        }

        $fields = $this->fields($sources['schema'], $issues);

        foreach ($this->templates->errors($sources['html'], $fields) as $error) {
            $issues[] = self::issue('html', $error['line'], null, $error['message']);
        }

        $this->checkElements($sources['html'], $issues);
        $this->checkActions($sources['html'], $fields, $issues);
        $this->checkExternal('html', $sources['html'], $issues);
        $this->checkExternal('css', $sources['css'], $issues);

        $order = array_flip(array_keys(self::FILES));
        usort($issues, fn (array $a, array $b): int => [$order[$a['source']], $a['line'] ?? 0] <=> [$order[$b['source']], $b['line'] ?? 0]);

        return $issues;
    }

    /**
     * Adds schema issues and returns the top-level fields when the canonical schema is valid.
     *
     * @param  list<array{source: string, line: int|null, path: string|null, message: string}>  $issues
     * @return list<array<string, mixed>>|null
     */
    private function fields(string $source, array &$issues): ?array
    {
        if (trim($source) === '') {
            $issues[] = self::issue('schema', null, 'schema', 'Схема пуста. Опишите поля блока в schema.json.');

            return null;
        }

        try {
            $schema = json_decode($source, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $issues[] = self::issue('schema', null, 'schema', 'schema.json содержит некорректный JSON.');

            return null;
        }

        $errors = $this->schemas->errors($schema);

        foreach ($errors as $path => $message) {
            $issues[] = self::issue('schema', null, $path, $message);
        }

        if ($errors !== [] || ! is_array($schema) || ! is_array($schema['fields'] ?? null)) {
            return null;
        }

        /** @var list<array<string, mixed>> $fields */
        $fields = $schema['fields'];

        return $fields;
    }

    /**
     * @param  list<array{source: string, line: int|null, path: string|null, message: string}>  $issues
     */
    private function checkElements(string $html, array &$issues): void
    {
        $pattern = '/<('.implode('|', self::FORBIDDEN_ELEMENTS).')(?=[\s\/>]|$)/i';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $element = strtolower($match[1][0]);
            $message = match ($element) {
                'script' => 'Тег <script> запрещён в шаблоне: код блока пишется в script.js.',
                'style' => 'Тег <style> запрещён в шаблоне: стили блока пишутся в styles.css.',
                default => "Тег <{$element}> запрещён в шаблоне блока.",
            };
            $issues[] = self::issue('html', self::line($html, $match[0][1]), null, $message);
        }
    }

    /**
     * Every `data-landflow-action` must name a top-level `action` field: the bridge accepts no other key.
     *
     * @param  list<array<string, mixed>>|null  $fields
     * @param  list<array{source: string, line: int|null, path: string|null, message: string}>  $issues
     */
    private function checkActions(string $html, ?array $fields, array &$issues): void
    {
        preg_match_all('/data-landflow-action\s*=\s*(?|"([^"]*)"|\'([^\']*)\'|([^\s>]*))/i', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $actions = [];

        foreach ($fields ?? [] as $field) {
            if (($field['type'] ?? null) === BlockFieldType::Action->value) {
                $actions[] = $field['key'];
            }
        }

        foreach ($matches as $match) {
            $key = trim($match[1][0] ?? '');
            $line = self::line($html, $match[0][1]);

            if ($key === '' || str_contains($key, '{{')) {
                $issues[] = self::issue('html', $line, null, 'Укажите в data-landflow-action ключ поля «Действие» явно, без подстановок {{ }}.');
            } elseif ($fields !== null && ! in_array($key, $actions, true)) {
                $issues[] = self::issue('html', $line, null, "Действие «{$key}» не описано в схеме: добавьте поле типа «Действие» с ключом «{$key}» на верхнем уровне.");
            }
        }
    }

    /**
     * External resources are blocked by the sandbox CSP anyway; the check tells the author before publishing.
     *
     * @param  list<array{source: string, line: int|null, path: string|null, message: string}>  $issues
     */
    private function checkExternal(string $source, string $code, array &$issues): void
    {
        $file = self::FILES[$source];
        $rules = [
            '/https?:\/\/[^\s"\'<>()]*/i' => fn (string $url): string => 'Внешний адрес «'.mb_strimwidth($url, 0, 60, '…')."» запрещён в {$file}: блок не загружает внешние ресурсы.",
            '/(?:\b(?:src|href|srcset|poster|action|formaction|data|background)\s*=\s*["\']?\s*|url\(\s*["\']?\s*)\/\//i' => fn (): string => "Адрес, начинающийся с //, запрещён в {$file}: блок не загружает внешние ресурсы.",
            '/@import\b/i' => fn (): string => "@import запрещён в {$file}: все стили блока должны находиться в styles.css.",
        ];

        foreach ($rules as $pattern => $message) {
            preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$text, $offset]) {
                if (self::isNamespace($code, $offset)) {
                    continue;
                }

                $issues[] = self::issue($source, self::line($code, $offset), null, $message($text));
            }
        }
    }

    /** Inline SVG namespace URIs (`xmlns="http://www.w3.org/2000/svg"`) are identifiers, never fetched. */
    private static function isNamespace(string $code, int $offset): bool
    {
        $before = substr($code, max(0, $offset - 40), min(40, $offset));

        return preg_match('/\bxmlns(?::[\w-]+)?\s*=\s*["\']?$/i', $before) === 1;
    }

    private static function line(string $code, int $offset): int
    {
        return substr_count($code, "\n", 0, $offset) + 1;
    }

    /**
     * @return array{source: string, line: int|null, path: string|null, message: string}
     */
    private static function issue(string $source, ?int $line, ?string $path, string $message): array
    {
        return ['source' => $source, 'line' => $line, 'path' => $path, 'message' => $message];
    }
}
