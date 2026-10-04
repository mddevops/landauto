<?php

namespace App\Forms;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Authoritative server-side validation of visitor field values against the current Form
 * definition. Client-side checks are UX only (FORMS_AND_INTEGRATIONS.md §5).
 */
class SubmissionFieldValidator
{
    public function __construct(private PhoneNormalizer $phones) {}

    /**
     * @param  array<array-key, mixed>  $input  Visitor `fields` object.
     * @return array{values: array<string, string|bool|null>, errors: array<string, string>}
     */
    public function validate(Form $form, array $input): array
    {
        /** @var list<FormField> $fields */
        $fields = array_values($form->fields->all());
        $rules = [];
        $attributes = [];
        $messages = [];
        $errors = [];

        foreach (array_keys($input) as $key) {
            if (! is_string($key) || ! $this->has($fields, $key)) {
                $errors[(string) $key] = 'Неизвестное поле формы.';
            }
        }

        foreach ($fields as $field) {
            $rules[$field->key] = $this->rules($field);
            $attributes[$field->key] = $field->type === FormFieldType::Consent ? 'Согласие' : Str::limit($field->label, 60);
            $messages["{$field->key}.accepted"] = $field->type === FormFieldType::Consent
                ? 'Подтвердите согласие.'
                : 'Отметьте этот пункт.';
        }

        $validator = Validator::make($input, $rules, $messages, $attributes);

        foreach ($validator->errors()->messages() as $key => $list) {
            $errors[$key] ??= $list[0];
        }

        if ($errors !== []) {
            return ['values' => [], 'errors' => $errors];
        }

        $values = [];

        foreach ($fields as $field) {
            $value = $input[$field->key] ?? null;
            $values[$field->key] = $field->type->isBoolean()
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : (is_scalar($value) && (string) $value !== '' ? (string) $value : null);
        }

        return ['values' => $values, 'errors' => []];
    }

    /**
     * @return list<mixed>
     */
    private function rules(FormField $field): array
    {
        if ($field->type->isBoolean()) {
            return $field->required ? ['accepted'] : ['nullable', 'boolean'];
        }

        $rules = [$field->required ? 'required' : 'nullable', 'string'];

        if (($max = $field->maxLength()) !== null) {
            $rules[] = "max:{$max}";
        }

        return match ($field->type) {
            FormFieldType::Phone => [...$rules, function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ($error = $this->phones->error($value)) !== null) {
                    $fail($error);
                }
            }],
            FormFieldType::Email => [...$rules, 'email:rfc'],
            FormFieldType::Select => [...$rules, Rule::in($field->options ?? [])],
            default => $rules,
        };
    }

    /**
     * @param  list<FormField>  $fields
     */
    private function has(array $fields, string $key): bool
    {
        foreach ($fields as $field) {
            if ($field->key === $key) {
                return true;
            }
        }

        return false;
    }
}
