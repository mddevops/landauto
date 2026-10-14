<?php

namespace App\Blocks\Native\Css;

/**
 * A CSS simple block (`{}`, `()`, `[]`) or function, with its nested component values.
 */
final readonly class CssBlock
{
    /**
     * @param  CssToken  $open  `{`, `(`, `[` or a function token
     * @param  list<CssToken|CssBlock>  $items
     */
    public function __construct(
        public CssToken $open,
        public array $items,
    ) {}

    public function isFunction(): bool
    {
        return $this->open->type === CssToken::FUNCTION;
    }

    public function close(): string
    {
        return match ($this->open->type) {
            CssToken::OPEN_CURLY => '}',
            CssToken::OPEN_SQUARE => ']',
            default => ')',
        };
    }
}
