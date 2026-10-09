<?php

namespace App\Blocks\Native;

use App\Blocks\BlockTemplateParser;
use App\Enums\BlockFieldType;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use LibXMLError;

/**
 * Compiles authored Native template HTML into a {@see NativeTemplate} with a real HTML parser
 * (libxml), never with regex rewriting (ADR-009). Template tags are first replaced by private-use
 * markers, so after parsing the compiler knows exactly where each expression landed: only text
 * nodes and attribute values may hold one. Tag names, attribute names, comments and structure
 * spanning several parents are rejected.
 */
final class NativeTemplateCompiler
{
    private const OPEN = "\u{E000}";

    private const CLOSE = "\u{E001}";

    private const MARKER = '/\x{E000}(\d+)\x{E001}/u';

    /** libxml HTML_UNKNOWN_TAG: HTML5 and SVG elements it does not know; the allowlist decides instead. */
    private const UNKNOWN_TAG = 801;

    /** @var list<array{kind: string, path: string|null}> */
    private array $tags = [];

    /** @var list<string> */
    private array $actionFields = [];

    /** @var list<string> */
    private array $actions = [];

    private int $consumed = 0;

    public function __construct(private BlockTemplateParser $parser) {}

    /**
     * @param  list<array<string, mixed>>  $fields  top-level schema fields of the version
     */
    public function compile(string $html, array $fields): NativeTemplate
    {
        if (str_contains($html, self::OPEN) || str_contains($html, self::CLOSE)) {
            throw NativeCompileException::html('index.html содержит служебные символы U+E000 или U+E001: удалите их.');
        }

        foreach ($this->parser->errors($html, $fields) as $error) {
            throw NativeCompileException::html($error['message'], $error['line']);
        }

        $this->tags = [];
        $this->actions = [];
        $this->actionFields = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? null) === BlockFieldType::Action->value && is_string($field['key'] ?? null)) {
                $this->actionFields[] = $field['key'];
            }
        }

        $marked = (string) preg_replace_callback('/\{\{(.*?)\}\}/s', function (array $match): string {
            $this->tags[] = self::tag(trim($match[1]));

            return self::OPEN.(count($this->tags) - 1).self::CLOSE;
        }, $html);

        if (preg_match('/<(?:[\/!?]\s*)?\x{E000}/u', $marked, $tagName, PREG_OFFSET_CAPTURE) === 1) {
            throw NativeCompileException::html('Подстановка {{ }} не может быть именем тега.', substr_count($marked, "\n", 0, $tagName[0][1]) + 1);
        }

        $this->consumed = 0;
        $nodes = $this->children($this->parse($marked));

        // The parser may drop malformed constructs silently; every expression must have landed somewhere.
        if ($this->consumed !== count($this->tags)) {
            throw NativeCompileException::html('Подстановка {{ }} находится в недопустимом месте разметки.');
        }

        return new NativeTemplate($nodes, array_values(array_unique($this->actions)));
    }

    /**
     * @return array{kind: string, path: string|null}
     */
    private static function tag(string $inner): array
    {
        return match (true) {
            preg_match('/^#(if|each)\s+(\S+)$/', $inner, $open) === 1 => ['kind' => $open[1], 'path' => $open[2]],
            $inner === 'else' => ['kind' => 'else', 'path' => null],
            preg_match('/^\/(if|each)$/', $inner, $close) === 1 => ['kind' => '/'.$close[1], 'path' => null],
            default => ['kind' => 'var', 'path' => $inner],
        };
    }

    private function parse(string $marked): DOMElement
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument;

        try {
            // The prefix holds no newline, so libxml line numbers are template line numbers.
            $document->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div>'.$marked.'</div></body></html>', LIBXML_NONET);
            $errors = array_values(array_filter(libxml_get_errors(), fn (LibXMLError $error): bool => $error->code !== self::UNKNOWN_TAG));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($errors !== []) {
            throw NativeCompileException::html(self::parseError($errors[0]), $errors[0]->line);
        }

        $head = $document->getElementsByTagName('head')->item(0);
        $body = $document->getElementsByTagName('body')->item(0);
        $root = $body?->firstChild;

        if ($head === null || $head->childNodes->length !== 1 || $body === null || $body->childNodes->length !== 1 || ! $root instanceof DOMElement) {
            throw NativeCompileException::html('Разметка выходит за пределы блока: проверьте лишние закрывающие теги и теги <html>, <head>, <body>.');
        }

        return $root;
    }

    private static function parseError(LibXMLError $error): string
    {
        $message = trim($error->message);

        return match (true) {
            preg_match('/^Unexpected end tag : ([a-z0-9]+)$/i', $message, $tag) === 1 => "Лишний или неверно вложенный закрывающий тег </{$tag[1]}>.",
            $error->code === 68 => 'Некорректное имя атрибута: подстановки {{ }} и служебные символы не могут быть именем атрибута.',
            default => 'Некорректная HTML-разметка: проверьте теги, кавычки атрибутов и сущности вида &amp;.',
        };
    }

    /**
     * @return list<NativeNode>
     */
    private function children(DOMNode $parent): array
    {
        $items = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMText) {
                array_push($items, ...$this->split($child->data));
            } elseif ($child instanceof DOMComment) {
                if (str_contains($child->data, self::OPEN)) {
                    throw NativeCompileException::html('Подстановки {{ }} внутри HTML-комментариев запрещены.', $child->getLineNo());
                }
            } elseif ($child instanceof DOMElement) {
                $items[] = $this->element($child);
            } else {
                throw NativeCompileException::html('Неподдерживаемая конструкция разметки.', $child->getLineNo());
            }
        }

        return $this->sections($items, $parent->getLineNo());
    }

    private function element(DOMElement $element): NativeNode
    {
        $name = strtolower($element->nodeName);
        $line = $element->getLineNo();
        $error = NativeHtmlPolicy::elementError($name);

        if ($error !== null) {
            throw NativeCompileException::html($error, $line);
        }

        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower($attribute->nodeName);
            $value = (string) $attribute->nodeValue;

            if (str_contains($attributeName, self::OPEN)) {
                throw NativeCompileException::html('Подстановка {{ }} не может быть именем атрибута.', $line);
            }

            $error = NativeHtmlPolicy::attributeError($name, $attributeName);

            if ($error !== null) {
                throw NativeCompileException::html($error, $line);
            }

            $attributes[] = match (true) {
                $attributeName === NativeHtmlPolicy::ACTION_ATTRIBUTE => $this->action($value, $line),
                $name === 'img' && $attributeName === 'src' => $this->imageSource($value, $line),
                default => $this->attribute($name, $attributeName, $value, $line),
            };
        }

        $children = in_array($name, NativeHtmlPolicy::VOID_ELEMENTS, true) ? [] : $this->children($element);

        return new NativeNode(NativeNode::ELEMENT, $name, $children, [], $attributes);
    }

    private function action(string $value, int $line): NativeAttribute
    {
        $key = trim($value);

        if ($key === '' || str_contains($key, self::OPEN)) {
            throw NativeCompileException::html('Укажите в data-landflow-action ключ поля «Действие» явно, без подстановок {{ }}.', $line);
        }

        if (! in_array($key, $this->actionFields, true)) {
            throw NativeCompileException::html("Действие «{$key}» не описано в схеме: добавьте поле типа «Действие» с ключом «{$key}» на верхнем уровне.", $line);
        }

        $this->actions[] = $key;

        return new NativeAttribute(NativeHtmlPolicy::ACTION_ATTRIBUTE, [new NativeNode(NativeNode::TEXT, $key)]);
    }

    /** Images load only from version-scoped asset URLs produced for an `image` field. */
    private function imageSource(string $value, int $line): NativeAttribute
    {
        if (preg_match('/^\s*'.self::OPEN.'(\d+)'.self::CLOSE.'\s*$/u', $value, $match) !== 1
            || $this->tags[(int) $match[1]]['kind'] !== 'var'
            || ! str_ends_with((string) $this->tags[(int) $match[1]]['path'], '.url')) {
            throw NativeCompileException::html('Изображение подключается только полем «Изображение»: src="{{ поле.url }}".', $line);
        }

        $this->consumed++;

        return new NativeAttribute('src', [new NativeNode(NativeNode::VAR, (string) $this->tags[(int) $match[1]]['path'])]);
    }

    private function attribute(string $element, string $name, string $value, int $line): NativeAttribute
    {
        if (! str_contains($value, self::OPEN)) {
            $error = NativeHtmlPolicy::valueError($element, $name, $value);

            if ($error !== null) {
                throw NativeCompileException::html($error, $line);
            }
        }

        return new NativeAttribute($name, $this->sections($this->split($value), $line));
    }

    /**
     * @return list<NativeNode|array{kind: string, path: string|null}>
     */
    private function split(string $text): array
    {
        $items = [];
        $parts = preg_split(self::MARKER, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                $items[] = $this->tags[(int) $part];
                $this->consumed++;
            } elseif ($part !== '') {
                $items[] = new NativeNode(NativeNode::TEXT, $part);
            }
        }

        return $items;
    }

    /**
     * Builds sections within one child list or one attribute value; the template parser already
     * proved global balance, so an imbalance here means a section crosses element boundaries.
     *
     * @param  list<NativeNode|array{kind: string, path: string|null}>  $items
     * @return list<NativeNode>
     */
    private function sections(array $items, int $line): array
    {
        /** @var list<array{kind: string|null, path: string, children: list<NativeNode>, otherwise: list<NativeNode>, else: bool}> $stack */
        $stack = [['kind' => null, 'path' => '', 'children' => [], 'otherwise' => [], 'else' => false]];
        $crossing = 'Конструкции {{#if}} и {{#each}} должны открываться и закрываться внутри одного элемента или одного значения атрибута.';

        foreach ($items as $item) {
            $top = count($stack) - 1;

            if ($item instanceof NativeNode || $item['kind'] === 'var') {
                $node = $item instanceof NativeNode ? $item : new NativeNode(NativeNode::VAR, (string) $item['path']);
                $stack[$top][$stack[$top]['else'] ? 'otherwise' : 'children'][] = $node;
            } elseif ($item['kind'] === 'if' || $item['kind'] === 'each') {
                $stack[] = ['kind' => $item['kind'], 'path' => (string) $item['path'], 'children' => [], 'otherwise' => [], 'else' => false];
            } elseif ($item['kind'] === 'else') {
                if ($stack[$top]['kind'] !== 'if') {
                    throw NativeCompileException::html($crossing, $line);
                }

                $stack[$top]['else'] = true;
            } else {
                $frame = array_pop($stack);

                if ($stack === [] || '/'.$frame['kind'] !== $item['kind']) {
                    throw NativeCompileException::html($crossing, $line);
                }

                $node = new NativeNode($frame['kind'] === 'if' ? NativeNode::IF : NativeNode::EACH, $frame['path'], $frame['children'], $frame['otherwise']);
                $parent = count($stack) - 1;
                $stack[$parent][$stack[$parent]['else'] ? 'otherwise' : 'children'][] = $node;
            }
        }

        if (count($stack) !== 1) {
            throw NativeCompileException::html($crossing, $line);
        }

        return $stack[0]['children'];
    }
}
