<?php

namespace App\Publishing\Rendering;

/**
 * Renders published Page payloads to HTML at Publish time (ADR-006 §2). Implementations get
 * only the sanitized public payloads and must not touch the database, network or secrets.
 */
interface PageRenderer
{
    /**
     * @param  list<array<string, mixed>>  $payloads  one hydration payload per Page
     * @return array<string, string> rendered body HTML keyed by Page public ID
     *
     * @throws PageRenderException
     */
    public function render(array $payloads): array;
}
