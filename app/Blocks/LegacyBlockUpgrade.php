<?php

namespace App\Blocks;

use App\Models\BlockVersion;

/**
 * Whether one Block Instance of a Site or Template Draft can move from its legacy sandboxed
 * version to the Native successor: the target with the migrated state, or a Russian reason.
 */
final readonly class LegacyBlockUpgrade
{
    /**
     * @param  array<string, mixed>  $state
     */
    private function __construct(
        public ?BlockVersion $target,
        public array $state,
        public ?string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public static function to(BlockVersion $target, array $state): self
    {
        return new self($target, $state, null);
    }

    public static function denied(string $reason, ?BlockVersion $target = null): self
    {
        return new self($target, [], $reason);
    }

    /**
     * @phpstan-assert-if-true !null $this->target
     */
    public function possible(): bool
    {
        return $this->reason === null && $this->target !== null;
    }
}
