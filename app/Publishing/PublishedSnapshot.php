<?php

namespace App\Publishing;

use App\Enums\PublishedAssetKind;

/**
 * Both representations of one Publish (ADR-006 §1): the sanitized public manifest and the
 * private restorable draft snapshot, plus the files the manifest renders.
 */
final readonly class PublishedSnapshot
{
    /**
     * @param  array<string, mixed>  $publicManifest
     * @param  array<string, mixed>  $draftSnapshot
     * @param  list<array{kind: PublishedAssetKind, public_id: string}>  $assetReferences
     */
    public function __construct(
        public array $publicManifest,
        public array $draftSnapshot,
        public string $manifestHash,
        public array $assetReferences,
    ) {}
}
