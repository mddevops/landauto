<?php

namespace App\Blocks\Native;

/**
 * A literal attribute name with a value made of text, `var` and section nodes (never elements).
 */
final readonly class NativeAttribute
{
    /**
     * @param  list<NativeNode>  $parts
     */
    public function __construct(
        public string $name,
        public array $parts,
    ) {}
}
