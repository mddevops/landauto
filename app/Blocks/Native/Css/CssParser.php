<?php

namespace App\Blocks\Native\Css;

use App\Blocks\Native\NativeCompileException;
use Closure;

/**
 * CSS Syntax Level 3 parsing (§5) of tokens into component values and rule lists. Error
 * recovery is intentionally absent: unbalanced blocks and stray closing braces fail the compile.
 */
final class CssParser
{
    /**
     * @param  list<CssToken>  $tokens
     * @param  Closure(int): int  $line  offset to source line
     * @return list<CssToken|CssBlock>
     */
    public function components(array $tokens, Closure $line): array
    {
        $position = 0;

        return $this->until($tokens, $position, null, $line);
    }

    /**
     * Splits component values into rules: at-rules end at `;` or a `{}` block, qualified rules
     * need a `{}` block.
     *
     * @param  list<CssToken|CssBlock>  $items
     * @param  Closure(int): int  $line
     * @return list<CssRule>
     */
    public function rules(array $items, Closure $line): array
    {
        $rules = [];
        $count = count($items);
        $index = 0;

        while ($index < $count) {
            $item = $items[$index];

            if ($item instanceof CssToken && ($item->isTrivia() || $item->type === CssToken::CDO || $item->type === CssToken::CDC)) {
                $index++;

                continue;
            }

            $at = $item instanceof CssToken && $item->type === CssToken::AT_KEYWORD ? $item : null;
            $offset = self::offset($item);
            $prelude = [];
            $block = null;
            $index += $at === null ? 0 : 1;

            while ($index < $count) {
                $current = $items[$index++];

                if ($current instanceof CssBlock && $current->open->type === CssToken::OPEN_CURLY) {
                    $block = $current;
                    break;
                }

                if ($at !== null && $current instanceof CssToken && $current->type === CssToken::SEMICOLON) {
                    break;
                }

                $prelude[] = $current;
            }

            if ($at === null && $block === null) {
                throw NativeCompileException::css('После селектора ожидается блок { … }.', $line($offset));
            }

            $rules[] = new CssRule($at, $prelude, $block, $offset);
        }

        return $rules;
    }

    /**
     * Rendering is verbatim: raw token text and block delimiters.
     *
     * @param  list<CssToken|CssBlock>  $items
     */
    public static function serialize(array $items): string
    {
        $css = '';

        foreach ($items as $item) {
            $css .= $item instanceof CssBlock
                ? $item->open->raw.self::serialize($item->items).$item->close()
                : $item->raw;
        }

        return $css;
    }

    /**
     * @param  list<CssToken|CssBlock>  $items
     * @return list<CssToken|CssBlock>
     */
    public static function trim(array $items): array
    {
        while ($items !== [] && $items[0] instanceof CssToken && $items[0]->isTrivia()) {
            array_shift($items);
        }

        while ($items !== [] && $items[array_key_last($items)] instanceof CssToken && $items[array_key_last($items)]->isTrivia()) {
            array_pop($items);
        }

        return $items;
    }

    public static function offset(CssToken|CssBlock $item): int
    {
        return $item instanceof CssBlock ? $item->open->offset : $item->offset;
    }

    /**
     * @param  list<CssToken>  $tokens
     * @param  Closure(int): int  $line
     * @return list<CssToken|CssBlock>
     */
    private function until(array $tokens, int &$position, ?string $close, Closure $line): array
    {
        $items = [];
        $count = count($tokens);

        while ($position < $count) {
            $token = $tokens[$position++];

            if ($close !== null && $token->type === $close) {
                return $items;
            }

            $nestedClose = match ($token->type) {
                CssToken::OPEN_CURLY => CssToken::CLOSE_CURLY,
                CssToken::OPEN_SQUARE => CssToken::CLOSE_SQUARE,
                CssToken::OPEN_PAREN, CssToken::FUNCTION => CssToken::CLOSE_PAREN,
                default => null,
            };

            if ($nestedClose !== null) {
                $items[] = new CssBlock($token, $this->until($tokens, $position, $nestedClose, $line));
            } elseif (in_array($token->type, [CssToken::CLOSE_CURLY, CssToken::CLOSE_SQUARE, CssToken::CLOSE_PAREN], true)) {
                throw NativeCompileException::css("Лишняя закрывающая скобка «{$token->raw}» в styles.css.", $line($token->offset));
            } else {
                $items[] = $token;
            }
        }

        if ($close !== null) {
            throw NativeCompileException::css('Не закрыта скобка в styles.css: проверьте парность {, (, [.', $line($tokens[$count - 1]->offset));
        }

        return $items;
    }
}
