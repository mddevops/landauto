<?php

namespace Database\Factories;

use App\Enums\FormFieldType;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => 'Обратный звонок',
            'status' => true,
            'submit_label' => Form::DEFAULT_SUBMIT_LABEL,
            'success_message' => Form::DEFAULT_SUCCESS_MESSAGE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => false]);
    }

    /**
     * Name, phone and required consent: the standard lead form.
     */
    public function withLeadFields(): static
    {
        return $this->afterCreating(function (Form $form): void {
            foreach ([
                ['key' => 'name', 'type' => FormFieldType::Text, 'label' => 'Имя', 'required' => false],
                ['key' => 'phone', 'type' => FormFieldType::Phone, 'label' => 'Телефон', 'required' => true],
                ['key' => 'consent', 'type' => FormFieldType::Consent, 'label' => 'Согласен на обработку данных', 'required' => true],
            ] as $position => $attributes) {
                FormField::factory()->for($form)->create([...$attributes, 'sort_order' => $position]);
            }
        });
    }
}
