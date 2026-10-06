<?php

namespace App\Domains\Ssl;

use Carbon\CarbonImmutable;

/**
 * Outcome of one provisioning attempt: metadata only.
 */
final readonly class SslResult
{
    public const ISSUED = 'issued';

    public const TEMPORARY = 'temporary';

    public const PERMANENT = 'permanent';

    private function __construct(public string $outcome, public ?CarbonImmutable $expiresAt = null) {}

    public static function issued(?CarbonImmutable $expiresAt): self
    {
        return new self(self::ISSUED, $expiresAt);
    }

    public static function temporary(): self
    {
        return new self(self::TEMPORARY);
    }

    public static function permanent(): self
    {
        return new self(self::PERMANENT);
    }
}
