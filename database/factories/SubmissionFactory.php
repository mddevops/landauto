<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'site_id' => fn (array $attributes) => Form::query()->whereKey($attributes['form_id'])->value('site_id'),
            'payload' => [
                ['key' => 'phone', 'type' => 'phone', 'label' => 'Телефон', 'value' => '+7 (999) 111-22-33'],
            ],
            'phone_original' => '+7 (999) 111-22-33',
            'phone_normalized' => '79991112233',
            'submitted_at' => now(),
        ];
    }
}
