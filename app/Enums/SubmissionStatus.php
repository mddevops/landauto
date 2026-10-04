<?php

namespace App\Enums;

/**
 * Submission lifecycle. Delivery states arrive with Integration delivery (Phase 6+).
 */
enum SubmissionStatus: string
{
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Получена',
        };
    }
}
