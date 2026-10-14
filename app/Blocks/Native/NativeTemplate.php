<?php

namespace App\Blocks\Native;

/**
 * A compiled Native template: a tree of allowlisted elements, literal text and template
 * expressions. Values can only ever land in text nodes or attribute values (ADR-009).
 */
final readonly class NativeTemplate
{
    /**
     * @param  list<NativeNode>  $nodes
     * @param  list<string>  $actions  top-level action field keys used by `data-landflow-action`
     */
    public function __construct(
        public array $nodes,
        public array $actions,
    ) {}
}
