<?php

namespace App\Forms;

use App\Enums\FormFieldType;
use App\Enums\SiteStatus;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Submission;
use Illuminate\Support\Str;

/**
 * Canonical public submission pipeline (FORMS_AND_INTEGRATIONS.md §9): resolve the Form,
 * validate, then persist the Submission before anything else may happen with it.
 */
class SubmissionPipeline
{
    /** Top-level payload keys a visitor may send; anything else is rejected. */
    public const PAYLOAD_KEYS = ['fields'];

    public function __construct(private SubmissionFieldValidator $validator) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function handle(string $formPublicId, array $payload, ?string $ip, ?string $userAgent): SubmissionResult
    {
        $form = Form::query()->with(['fields', 'site'])->where('public_id', $formPublicId)->first();

        if ($form === null || ! $form->status || $form->site->status !== SiteStatus::Active) {
            return SubmissionResult::unavailable();
        }

        $fields = $payload['fields'] ?? null;

        if (array_diff(array_keys($payload), self::PAYLOAD_KEYS) !== [] || ! is_array($fields)) {
            return SubmissionResult::invalid([]);
        }

        $validated = $this->validator->validate($form, $fields);

        if ($validated['errors'] !== []) {
            return SubmissionResult::invalid($validated['errors']);
        }

        $submission = $this->persist($form, $validated['values'], $ip, $userAgent);

        return SubmissionResult::accepted($submission, $form->success_message);
    }

    /**
     * @param  array<string, string|bool|null>  $values
     */
    private function persist(Form $form, array $values, ?string $ip, ?string $userAgent): Submission
    {
        $phone = $this->firstValue($form, $values, FormFieldType::Phone);
        $email = $this->firstValue($form, $values, FormFieldType::Email);

        $submission = new Submission([]);
        $submission->forceFill([
            'site_id' => $form->site_id,
            'form_id' => $form->id,
            'payload' => array_values($form->fields->map(fn (FormField $field): array => [
                'key' => $field->key,
                'type' => $field->type->value,
                'label' => $field->label,
                'value' => $values[$field->key] ?? null,
            ])->all()),
            'phone_original' => $phone,
            'email_normalized' => $email !== null ? Str::lower($email) : null,
            'ip' => $ip,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'submitted_at' => now(),
        ])->save();

        return $submission;
    }

    /**
     * @param  array<string, string|bool|null>  $values
     */
    private function firstValue(Form $form, array $values, FormFieldType $type): ?string
    {
        foreach ($form->fields as $field) {
            if ($field->type === $type && is_string($values[$field->key] ?? null)) {
                return $values[$field->key];
            }
        }

        return null;
    }
}
