<?php

namespace App\Publishing;

/**
 * Result of validating a Site Draft for Publish. Errors block Publish; warnings do not.
 */
final readonly class PublishValidation
{
    /**
     * @param  list<PublishIssue>  $errors
     * @param  list<PublishIssue>  $warnings
     */
    public function __construct(
        public array $errors,
        public array $warnings,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<string>
     */
    public function errorCodes(): array
    {
        return array_values(array_unique(array_map(fn (PublishIssue $issue): string => $issue->code, $this->errors)));
    }

    /**
     * @return array{errors: list<array<string, string|null>>, warnings: list<array<string, string|null>>}
     */
    public function toArray(): array
    {
        return [
            'errors' => array_map(fn (PublishIssue $issue): array => $issue->toArray(), $this->errors),
            'warnings' => array_map(fn (PublishIssue $issue): array => $issue->toArray(), $this->warnings),
        ];
    }
}
