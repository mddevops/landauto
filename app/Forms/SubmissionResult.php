<?php

namespace App\Forms;

use App\Models\Submission;

/**
 * Outcome of the public submission pipeline, mapped to an HTTP response by the controller.
 */
final readonly class SubmissionResult
{
    /**
     * @param  array<string, string>  $errors
     */
    private function __construct(
        public int $status,
        public string $message,
        public array $errors = [],
        public ?Submission $submission = null,
    ) {}

    public static function accepted(Submission $submission, string $message): self
    {
        return new self(201, $message, submission: $submission);
    }

    /**
     * @param  array<string, string>  $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(422, 'Проверьте правильность заполнения формы.', $errors);
    }

    public static function rejected(string $message): self
    {
        return new self(422, $message);
    }

    public static function throttled(string $message): self
    {
        return new self(429, $message);
    }

    public static function unavailable(): self
    {
        return new self(404, 'Форма недоступна.');
    }
}
