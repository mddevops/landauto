<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'key' => 'field_'.fake()->unique()->numberBetween(1, 99999),
            'type' => FormFieldType::Text,
            'label' => 'Поле',
            'placeholder' => null,
            'default_value' => null,
            'required' => false,
            'validation' => null,
            'options' => null,
            'sort_order' => 0,
        ];
    }
}
