<?php

namespace App\Blocks\Native\Css;

/**
 * An at-rule (`at` is its at-keyword) or a qualified rule (`at` is null) of a rule list.
 */
final readonly class CssRule
{
    /**
     * @param  list<CssToken|CssBlock>  $prelude
     */
    public function __construct(
        public ?CssToken $at,
        public array $prelude,
        public ?CssBlock $block,
        public int $offset,
    ) {}
}
