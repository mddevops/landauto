<?php

namespace App\Blocks\Native\Css;

use App\Blocks\Native\NativeCompileException;
use Closure;

/**
 * Scopes a parsed selector list under one Native Block version root (ADR-009). Works on
 * component values, never on selector text: every complex selector is split into compounds and
 * combinators; leading `:root`, `:scope`, `html` and `body` compounds become the scope itself,
 * every other selector becomes a descendant of the scope.
 */
final class CssSelectorScoper
{
    private const COMBINATORS = ['>', '+', '~'];

    private const COMPOUND_DELIMS = ['.', '*'];

    private const PSEUDO_FUNCTIONS = ['not', 'is', 'where', 'has', 'nth-child', 'nth-last-child', 'nth-of-type', 'nth-last-of-type', 'lang', 'dir'];

    private const GLOBAL_TYPES = ['html', 'body'];

    private const GLOBAL_PSEUDOS = ['root', 'scope'];

    /**
     * @param  list<CssToken|CssBlock>  $prelude
     * @param  Closure(int): int  $line
     */
    public function scope(array $prelude, string $scope, Closure $line): string
    {
        $selectors = [];
        $current = [];

        foreach ([...$prelude, null] as $item) {
            if ($item === null || ($item instanceof CssToken && $item->type === CssToken::COMMA)) {
                $selectors[] = $this->complex(CssParser::trim($current), $scope, $line, self::offsetOf($prelude));
                $current = [];
            } else {
                $current[] = $item;
            }
        }

        return implode(',', $selectors);
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     * @param  Closure(int): int  $line
     */
    private function complex(array $items, string $scope, Closure $line, int $fallback): string
    {
        if ($items === []) {
            throw NativeCompileException::css('Пустой селектор в styles.css.', $line($fallback));
        }

        foreach ($items as $item) {
            $this->assertSelectorItem($item, $line, false);
        }

        $compounds = $this->compounds($items, $line);
        $leading = 0;

        while ($leading < count($compounds) && $this->isGlobal($compounds[$leading]['items'])) {
            if ($leading > 0 && in_array($compounds[$leading]['combinator'], ['+', '~'], true)) {
                throw NativeCompileException::css('Комбинаторы + и ~ после :root, html или body выходят за пределы блока.', $line(CssParser::offset($items[0])));
            }

            $leading++;
        }

        if ($leading === 0) {
            return $scope.' '.$this->serializeCompounds($compounds, 0);
        }

        if ($leading < count($compounds) && in_array($compounds[$leading]['combinator'], ['+', '~'], true)) {
            throw NativeCompileException::css('Комбинаторы + и ~ после :root, html или body выходят за пределы блока.', $line(CssParser::offset($items[0])));
        }

        $type = '';
        $rest = '';

        foreach (array_slice($compounds, 0, $leading) as $compound) {
            [$compoundType, $compoundRest] = $this->globalParts($compound['items']);
            $type = $type === '' ? $compoundType : $type;
            $rest .= $compoundRest;
        }

        return $type.$scope.$rest.$this->serializeCompounds($compounds, $leading);
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     * @param  Closure(int): int  $line
     * @return list<array{combinator: string|null, items: list<CssToken|CssBlock>}>
     */
    private function compounds(array $items, Closure $line): array
    {
        $compounds = [];
        $current = [];
        $combinator = null;
        $whitespace = false;

        foreach ($items as $item) {
            if ($item instanceof CssToken && $item->type === CssToken::WHITESPACE) {
                $whitespace = $current !== [];

                continue;
            }

            if ($item instanceof CssToken && $item->type === CssToken::DELIM && in_array($item->value, self::COMBINATORS, true)) {
                if ($current === [] && ($compounds === [] || $combinator !== null)) {
                    throw NativeCompileException::css("Селектор не может начинаться с комбинатора «{$item->value}» или содержать два комбинатора подряд.", $line($item->offset));
                }

                if ($current !== []) {
                    $compounds[] = ['combinator' => $combinator, 'items' => $current];
                    $current = [];
                }

                $combinator = $item->value;
                $whitespace = false;

                continue;
            }

            if ($whitespace) {
                $compounds[] = ['combinator' => $combinator, 'items' => $current];
                $current = [];
                $combinator = ' ';
            }

            $whitespace = false;
            $current[] = $item;
        }

        if ($current === []) {
            throw NativeCompileException::css('Селектор не может заканчиваться комбинатором.', $line(CssParser::offset($items[0])));
        }

        $compounds[] = ['combinator' => $combinator, 'items' => $current];

        return $compounds;
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     */
    private function isGlobal(array $items): bool
    {
        $significant = self::significant($items);
        $first = $significant[0] ?? null;

        if ($first instanceof CssToken && $first->type === CssToken::IDENT && in_array(strtolower($first->value), self::GLOBAL_TYPES, true)) {
            return true;
        }

        return $this->globalPseudoPositions($significant) !== [];
    }

    /**
     * The compound split into its type selector (kept in front of the scope) and the rest,
     * with `html`, `body`, `*`, `:root` and `:scope` removed.
     *
     * @param  list<CssToken|CssBlock>  $items
     * @return array{0: string, 1: string}
     */
    private function globalParts(array $items): array
    {
        $significant = self::significant($items);
        $skip = array_flip($this->globalPseudoPositions($significant));
        $type = '';
        $rest = [];

        foreach ($significant as $index => $item) {
            if (isset($skip[$index])) {
                continue;
            }

            if ($index === 0 && $item instanceof CssToken && ($item->type === CssToken::IDENT || ($item->type === CssToken::DELIM && $item->value === '*'))) {
                if ($item->type === CssToken::IDENT && ! in_array(strtolower($item->value), self::GLOBAL_TYPES, true)) {
                    $type = $item->raw;
                }

                continue;
            }

            $rest[] = $item;
        }

        return [$type, CssParser::serialize($rest)];
    }

    /**
     * Positions of `:` + `root|scope` pairs (single-colon pseudo-classes only).
     *
     * @param  list<CssToken|CssBlock>  $items
     * @return list<int>
     */
    private function globalPseudoPositions(array $items): array
    {
        $positions = [];

        foreach ($items as $index => $item) {
            $next = $items[$index + 1] ?? null;
            $previous = $items[$index - 1] ?? null;

            if ($item instanceof CssToken && $item->type === CssToken::COLON
                && ! ($previous instanceof CssToken && $previous->type === CssToken::COLON)
                && $next instanceof CssToken && $next->type === CssToken::IDENT
                && in_array(strtolower($next->value), self::GLOBAL_PSEUDOS, true)) {
                array_push($positions, $index, $index + 1);
            }
        }

        return $positions;
    }

    /**
     * @param  list<array{combinator: string|null, items: list<CssToken|CssBlock>}>  $compounds
     */
    private function serializeCompounds(array $compounds, int $from): string
    {
        $css = '';

        foreach (array_slice($compounds, $from) as $index => $compound) {
            $combinator = $compound['combinator'];

            if ($index > 0 || $from > 0) {
                $css .= $combinator === ' ' || $combinator === null ? ' ' : " {$combinator} ";
            }

            $css .= CssParser::serialize($compound['items']);
        }

        return $css;
    }

    /**
     * @param  Closure(int): int  $line
     */
    private function assertSelectorItem(CssToken|CssBlock $item, Closure $line, bool $nested): void
    {
        if ($item instanceof CssBlock) {
            $name = strtolower($item->open->value);

            if ($item->open->type === CssToken::OPEN_SQUARE || ($item->isFunction() && in_array($name, self::PSEUDO_FUNCTIONS, true))) {
                foreach ($item->items as $child) {
                    $this->assertSelectorItem($child, $line, true);
                }

                return;
            }

            $message = $item->isFunction() ? "Функция «{$item->open->value}()» не поддерживается в селекторах нативного блока." : 'Некорректный селектор в styles.css.';

            throw NativeCompileException::css($message, $line($item->open->offset));
        }

        $allowed = match ($item->type) {
            CssToken::IDENT, CssToken::HASH, CssToken::COLON, CssToken::WHITESPACE, CssToken::COMMENT => true,
            CssToken::DELIM => in_array($item->value, [...self::COMBINATORS, ...self::COMPOUND_DELIMS], true)
                || ($nested && in_array($item->value, ['=', '~', '|', '^', '$', '*', '+', '-'], true)),
            CssToken::STRING, CssToken::NUMBER, CssToken::DIMENSION, CssToken::PERCENTAGE, CssToken::COMMA => $nested,
            default => false,
        };

        if (! $allowed) {
            $message = $item->type === CssToken::DELIM && $item->value === '&'
                ? 'Вложенный селектор & не поддерживается в нативных стилях.'
                : "Недопустимый фрагмент «{$item->raw}» в селекторе styles.css.";

            throw NativeCompileException::css($message, $line($item->offset));
        }
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     * @return list<CssToken|CssBlock>
     */
    private static function significant(array $items): array
    {
        return array_values(array_filter($items, fn (CssToken|CssBlock $item): bool => ! ($item instanceof CssToken && $item->type === CssToken::COMMENT)));
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     */
    private static function offsetOf(array $items): int
    {
        return $items === [] ? 0 : CssParser::offset($items[0]);
    }
}
