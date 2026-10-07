<?php

namespace App\Http\Requests\Blocks;

use App\Models\BlockDefinition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name and globally unique technical slug only. Ownership is never accepted from the browser.
 */
class StoreBlockDefinitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => trim((string) $this->input('slug')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.BlockDefinition::NAME_MAX],
            'slug' => [
                'required',
                'string',
                'min:'.BlockDefinition::SLUG_MIN,
                'max:'.BlockDefinition::SLUG_MAX,
                'regex:'.BlockDefinition::SLUG_PATTERN,
                Rule::unique('block_definitions', 'slug'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и одиночные дефисы между ними.',
            'slug.unique' => 'Этот slug уже используется другим блоком.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Название', 'slug' => 'Slug'];
    }
}
