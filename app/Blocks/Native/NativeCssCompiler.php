<?php

namespace App\Blocks\Native;

use App\Blocks\Native\Css\CssBlock;
use App\Blocks\Native\Css\CssParser;
use App\Blocks\Native\Css\CssRule;
use App\Blocks\Native\Css\CssSelectorScoper;
use App\Blocks\Native\Css\CssToken;
use App\Blocks\Native\Css\CssTokenizer;
use Closure;

/**
 * Compiles a Native Block stylesheet into version-scoped CSS (ADR-009) through a CSS Syntax
 * Level 3 tokenizer and parser: selectors are scoped by {@see CssSelectorScoper}, `@media` and
 * `@supports` keep their conditions with scoped contents, `@keyframes` names get a version prefix
 * and `animation` references follow. Network-capable constructs are denied: `@import`,
 * `@font-face`, `url()` and image functions; unknown at-rules and nested rules fail the compile.
 */
final class NativeCssCompiler
{
    private const MAX_DEPTH = 4;

    private const KEYFRAMES = ['keyframes', '-webkit-keyframes'];

    private const CONDITIONAL = ['media', 'supports'];

    private const ANIMATION_PROPERTIES = ['animation', 'animation-name', '-webkit-animation', '-webkit-animation-name'];

    private const DENIED_FUNCTIONS = ['url', 'src', 'image', 'image-set', '-webkit-image-set', 'cross-fade', '-webkit-cross-fade', 'element', '-moz-element', 'expression'];

    private const DENIED_PROPERTIES = ['behavior', '-moz-binding'];

    /** @var array<string, string> author keyframes name => scoped name */
    private array $keyframes = [];

    private string $scope = '';

    /** @var Closure(int): int */
    private Closure $line;

    public function __construct(
        private CssTokenizer $tokenizer,
        private CssParser $parser,
        private CssSelectorScoper $selectors,
    ) {}

    /**
     * @param  string  $scope  the scope selector, e.g. `[data-landflow-native="hero--1-0-0--abc"]`
     * @param  string  $keyframePrefix  deterministic per version, e.g. `lf-abc-`
     */
    public function compile(string $css, string $scope, string $keyframePrefix): string
    {
        $tokens = $this->tokenizer->tokenize($css);
        $this->line = fn (int $offset): int => $this->tokenizer->line($offset);
        $this->scope = $scope;
        $rules = $this->parser->rules($this->parser->components($tokens, $this->line), $this->line);

        $this->keyframes = [];

        foreach ($this->keyframeNames($rules, 0) as $name) {
            $this->keyframes[$name] = $keyframePrefix.$name;
        }

        return implode("\n", $this->emit($rules, 0));
    }

    /**
     * @param  list<CssRule>  $rules
     * @return list<string>
     */
    private function keyframeNames(array $rules, int $depth): array
    {
        $names = [];

        foreach ($rules as $rule) {
            $name = $rule->at === null ? null : strtolower($rule->at->value);

            if (in_array($name, self::KEYFRAMES, true)) {
                $names[] = $this->keyframesName($rule);
            } elseif (in_array($name, self::CONDITIONAL, true) && $rule->block !== null && $depth < self::MAX_DEPTH) {
                array_push($names, ...$this->keyframeNames($this->parser->rules($rule->block->items, $this->line), $depth + 1));
            }
        }

        return $names;
    }

    /**
     * @param  list<CssRule>  $rules
     * @return list<string>
     */
    private function emit(array $rules, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw NativeCompileException::css('Слишком глубокая вложенность @media и @supports в styles.css.', ($this->line)($rules[0]->offset ?? 0));
        }

        $css = [];

        foreach ($rules as $rule) {
            $css[] = $rule->at === null ? $this->styleRule($rule) : $this->atRule($rule, $rule->at, $depth);
        }

        return $css;
    }

    private function styleRule(CssRule $rule): string
    {
        $block = $rule->block;

        if ($block === null) {
            throw NativeCompileException::css('После селектора ожидается блок { … }.', ($this->line)($rule->offset));
        }

        return $this->selectors->scope($rule->prelude, $this->scope, $this->line).'{'.$this->declarations($block->items).'}';
    }

    private function atRule(CssRule $rule, CssToken $at, int $depth): string
    {
        $name = strtolower($at->value);
        $line = ($this->line)($rule->offset);

        if ($name === 'import') {
            throw NativeCompileException::css('@import запрещён в styles.css: все стили блока должны находиться в styles.css.', $line);
        }

        if ($name === 'font-face') {
            throw NativeCompileException::css('@font-face запрещён в нативном блоке: шрифты подключаются в настройках дизайна сайта.', $line);
        }

        if (! in_array($name, [...self::CONDITIONAL, ...self::KEYFRAMES], true)) {
            throw NativeCompileException::css("Правило @{$at->value} не поддерживается в нативном блоке.", $line);
        }

        if ($rule->block === null) {
            throw NativeCompileException::css("Правилу @{$at->value} нужен блок { … }.", $line);
        }

        if (in_array($name, self::KEYFRAMES, true)) {
            return $at->raw.' '.$this->keyframes[$this->keyframesName($rule)].'{'.$this->keyframeRules($rule->block).'}';
        }

        $prelude = CssParser::trim($rule->prelude);
        $this->assertValue($prelude);

        if ($prelude === []) {
            throw NativeCompileException::css("Укажите условие для @{$at->value}.", $line);
        }

        return $at->raw.' '.CssParser::serialize($prelude).'{'.implode('', $this->emit($this->parser->rules($rule->block->items, $this->line), $depth + 1)).'}';
    }

    private function keyframesName(CssRule $rule): string
    {
        $prelude = array_values(array_filter($rule->prelude, fn (CssToken|CssBlock $item): bool => ! ($item instanceof CssToken && $item->isTrivia())));

        if (count($prelude) !== 1 || ! $prelude[0] instanceof CssToken || $prelude[0]->type !== CssToken::IDENT || $prelude[0]->raw !== $prelude[0]->value) {
            throw NativeCompileException::css('Имя @keyframes укажите одним идентификатором из латинских букв, цифр, «-» и «_».', ($this->line)($rule->offset));
        }

        return $prelude[0]->value;
    }

    private function keyframeRules(CssBlock $block): string
    {
        $css = '';

        foreach ($this->parser->rules($block->items, $this->line) as $frame) {
            $selector = CssParser::trim($frame->prelude);

            foreach ($selector as $item) {
                $valid = $item instanceof CssToken && (
                    $item->isTrivia() || $item->type === CssToken::PERCENTAGE || $item->type === CssToken::COMMA
                    || ($item->type === CssToken::IDENT && in_array(strtolower($item->value), ['from', 'to'], true))
                );

                if (! $valid) {
                    throw NativeCompileException::css('Кадры @keyframes задаются процентами, from или to.', ($this->line)($frame->offset));
                }
            }

            if ($frame->at !== null || $frame->block === null || $selector === []) {
                throw NativeCompileException::css('Кадры @keyframes задаются процентами, from или to.', ($this->line)($frame->offset));
            }

            $css .= CssParser::serialize($selector).'{'.$this->declarations($frame->block->items).'}';
        }

        return $css;
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     */
    private function declarations(array $items): string
    {
        $declarations = [];
        $current = [];

        foreach ([...$items, null] as $item) {
            if ($item !== null && ! ($item instanceof CssToken && $item->type === CssToken::SEMICOLON)) {
                $current[] = $item;

                continue;
            }

            $declaration = CssParser::trim($current);
            $current = [];

            if ($declaration !== []) {
                $declarations[] = $this->declaration($declaration);
            }
        }

        return implode(';', $declarations);
    }

    /**
     * @param  non-empty-list<CssToken|CssBlock>  $items
     */
    private function declaration(array $items): string
    {
        $property = $items[0];
        $line = ($this->line)(CssParser::offset($property));
        $index = 1;

        while (isset($items[$index]) && $items[$index] instanceof CssToken && $items[$index]->isTrivia()) {
            $index++;
        }

        $colon = $items[$index] ?? null;

        if (! $property instanceof CssToken || $property->type !== CssToken::IDENT || ! $colon instanceof CssToken || $colon->type !== CssToken::COLON) {
            throw NativeCompileException::css('Некорректное объявление в styles.css: ожидается «свойство: значение». Вложенные правила внутри { } не поддерживаются.', $line);
        }

        $name = strtolower($property->value);

        if (in_array($name, self::DENIED_PROPERTIES, true)) {
            throw NativeCompileException::css("Свойство {$property->value} запрещено в нативных стилях.", $line);
        }

        $value = CssParser::trim(array_slice($items, $index + 1));
        $this->assertValue($value);

        if (in_array($name, self::ANIMATION_PROPERTIES, true)) {
            $value = array_map(fn (CssToken|CssBlock $item): CssToken|CssBlock => $item instanceof CssToken && $item->type === CssToken::IDENT && isset($this->keyframes[$item->value])
                ? $item->withRaw($this->keyframes[$item->value])
                : $item, $value);
        }

        return $property->raw.':'.CssParser::serialize($value);
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     */
    private function assertValue(array $items): void
    {
        foreach ($items as $item) {
            if ($item instanceof CssBlock) {
                $function = strtolower($item->open->value);

                if ($item->open->type === CssToken::OPEN_CURLY) {
                    throw NativeCompileException::css('Блоки { } внутри значений не поддерживаются в нативных стилях.', ($this->line)($item->open->offset));
                }

                if ($item->isFunction() && in_array($function, self::DENIED_FUNCTIONS, true)) {
                    throw NativeCompileException::css("Функция {$item->open->value}() запрещена в нативных стилях: блок не загружает внешние ресурсы, изображения задаются полями блока.", ($this->line)($item->open->offset));
                }

                $this->assertValue($item->items);

                continue;
            }

            $message = match ($item->type) {
                CssToken::URL, CssToken::BAD_URL => 'url() запрещён в нативных стилях: блок не загружает внешние ресурсы, изображения задаются полями блока.',
                CssToken::AT_KEYWORD => "Правило {$item->raw} не может находиться внутри значения.",
                CssToken::CDO, CssToken::CDC => 'HTML-комментарии <!-- --> запрещены в styles.css.',
                default => null,
            };

            if ($message !== null) {
                throw NativeCompileException::css($message, ($this->line)($item->offset));
            }
        }
    }
}
