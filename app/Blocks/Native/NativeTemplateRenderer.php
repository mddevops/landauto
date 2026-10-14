<?php

namespace App\Blocks\Native;

use stdClass;

/**
 * Serializes a compiled Native template with Instance props into a safe HTML fragment. Every
 * value is escaped for its context: text nodes escape `& < >`, attribute values are always
 * double-quoted and escape `& " ' < >`, and URL-bearing attributes are re-checked after
 * substitution. Lookup and truthiness follow the sandbox template runtime.
 */
final class NativeTemplateRenderer
{
    public const MAX_BYTES = 512 * 1024;

    /** @var list<string> */
    private array $trustedUrls = [];

    /**
     * @param  list<string>  $trustedUrls  the only values an image `src` may take
     */
    public function render(NativeTemplate $template, stdClass $props, array $trustedUrls): string
    {
        $this->trustedUrls = $trustedUrls;
        $html = $this->nodes($template->nodes, [$props]);

        if (strlen($html) > self::MAX_BYTES) {
            throw NativeCompileException::state('Разметка блока после подстановки данных больше 512 КБ: сократите содержимое.');
        }

        return $html;
    }

    /**
     * @param  list<NativeNode>  $nodes
     * @param  list<mixed>  $scopes
     */
    private function nodes(array $nodes, array $scopes): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= match ($node->kind) {
                NativeNode::TEXT => self::text($node->value),
                NativeNode::VAR => self::text(self::string(self::lookup($node->value, $scopes))),
                NativeNode::ELEMENT => $this->element($node, $scopes),
                default => $this->section($node, $scopes, fn (array $children, array $scope): string => $this->nodes($children, $scope)),
            };
        }

        return $html;
    }

    /**
     * @param  list<mixed>  $scopes
     * @param  callable(list<NativeNode>, list<mixed>): string  $render
     */
    private function section(NativeNode $node, array $scopes, callable $render): string
    {
        $value = self::lookup($node->value, $scopes);

        if ($node->kind === NativeNode::IF) {
            return $render(self::truthy($value) ? $node->children : $node->otherwise, $scopes);
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return '';
        }

        $html = '';

        foreach ($value as $item) {
            $html .= $render($node->children, [...$scopes, $item]);
        }

        return $html;
    }

    /**
     * @param  list<mixed>  $scopes
     */
    private function element(NativeNode $node, array $scopes): string
    {
        $html = '<'.$node->value;

        foreach ($node->attributes as $attribute) {
            $value = $this->value($attribute->parts, $scopes);

            if ($node->value === 'img' && $attribute->name === 'src') {
                if ($value === '') {
                    continue;
                }

                if (! in_array($value, $this->trustedUrls, true)) {
                    throw NativeCompileException::state('Адрес изображения должен приходить из поля «Изображение».');
                }
            } elseif ($attribute->name !== NativeHtmlPolicy::ACTION_ATTRIBUTE) {
                $error = NativeHtmlPolicy::valueError($node->value, $attribute->name, $value);

                if ($error !== null) {
                    throw NativeCompileException::state($error);
                }
            }

            $html .= ' '.$attribute->name.'="'.htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        if (in_array($node->value, NativeHtmlPolicy::VOID_ELEMENTS, true)) {
            return $html.'>';
        }

        return $html.'>'.$this->nodes($node->children, $scopes).'</'.$node->value.'>';
    }

    /**
     * The raw (unescaped) attribute value; the caller checks and escapes it once.
     *
     * @param  list<NativeNode>  $parts
     * @param  list<mixed>  $scopes
     */
    private function value(array $parts, array $scopes): string
    {
        $value = '';

        foreach ($parts as $part) {
            $value .= match ($part->kind) {
                NativeNode::TEXT => $part->value,
                NativeNode::VAR => self::string(self::lookup($part->value, $scopes)),
                default => $this->section($part, $scopes, fn (array $children, array $scope): string => $this->value($children, $scope)),
            };
        }

        return $value;
    }

    /**
     * @param  list<mixed>  $scopes
     */
    private static function lookup(string $path, array $scopes): mixed
    {
        $segments = explode('.', $path);
        $value = null;

        foreach (array_reverse($scopes) as $scope) {
            if ($scope instanceof stdClass && property_exists($scope, $segments[0])) {
                $value = $scope->{$segments[0]};
                break;
            }
        }

        foreach (array_slice($segments, 1) as $segment) {
            $value = $value instanceof stdClass && property_exists($value, $segment) ? $value->{$segment} : null;
        }

        return $value;
    }

    private static function truthy(mixed $value): bool
    {
        return match (true) {
            is_array($value) => $value !== [],
            is_string($value) => $value !== '',
            is_float($value) => $value !== 0.0 && ! is_nan($value),
            default => (bool) $value,
        };
    }

    /** JavaScript `String(value)` for template values; objects and lists render nothing. */
    private static function string(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::number($value),
            default => '',
        };
    }

    private static function number(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'Infinity' : '-Infinity';
        }

        if (floor($value) === $value && abs($value) < 1e21) {
            return number_format($value, 0, '.', '');
        }

        return str_replace('.0e', 'e', (string) json_encode($value));
    }

    private static function text(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
