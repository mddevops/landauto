<?php

namespace App\Publishing;

use App\Enums\PublicationStatus;
use App\Models\Publication;
use App\Models\PublishedVersion;

/**
 * Result of one Publish attempt: the finished Publication, the validation (when it ran) and the
 * activated version on success.
 */
final readonly class PublishOutcome
{
    public function __construct(
        public Publication $publication,
        public ?PublishValidation $validation = null,
        public ?PublishedVersion $version = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->publication->status === PublicationStatus::Succeeded && $this->version !== null;
    }
}
