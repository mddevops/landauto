<?php

namespace App\Forms;

use App\Models\Form;
use App\Models\FormField;

/**
 * Visitor-facing Form payload: public ID, field keys and display settings only. No internal
 * IDs, routing, destinations or credentials (FORMS_AND_INTEGRATIONS.md §38, §41).
 */
final class FormRuntime
{
    /**
     * @return array<string, mixed>
     */
    public function present(Form $form): array
    {
        return [
            'public_id' => $form->public_id,
            'submit_label' => $form->submit_label,
            'success_message' => $form->success_message,
            'fields' => array_values($form->fields->map(fn (FormField $field): array => [
                'key' => $field->key,
                'type' => $field->type->value,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'default_value' => $field->default_value,
                'required' => $field->required,
                'max_length' => $field->maxLength(),
                'options' => $field->options ?? [],
            ])->all()),
        ];
    }
}
