<?php

namespace App\Blocks\Native;

/**
 * One node of a compiled Native template.
 *
 * - `text`: decoded literal text in `value`;
 * - `var`: escaped lookup of the path in `value`;
 * - `if`: path in `value`, branches in `children` / `otherwise`;
 * - `each`: repeater path in `value`, body in `children`;
 * - `element`: allowlisted element name in `value`, `attributes` and `children`.
 */
final readonly class NativeNode
{
    public const TEXT = 'text';

    public const VAR = 'var';

    public const IF = 'if';

    public const EACH = 'each';

    public const ELEMENT = 'element';

    /**
     * @param  self::TEXT|self::VAR|self::IF|self::EACH|self::ELEMENT  $kind
     * @param  list<NativeNode>  $children
     * @param  list<NativeNode>  $otherwise
     * @param  list<NativeAttribute>  $attributes
     */
    public function __construct(
        public string $kind,
        public string $value,
        public array $children = [],
        public array $otherwise = [],
        public array $attributes = [],
    ) {}
}
