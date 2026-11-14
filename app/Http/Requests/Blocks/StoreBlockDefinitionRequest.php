<?php

namespace App\Http\Requests\Blocks;

use App\Models\BlockDefinition;
use App\Models\BlockGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name, group and globally unique technical slug. Ownership is never accepted from the browser.
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
            'group' => ['required', 'string', Rule::exists('block_groups', 'public_id')],
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
            'group.required' => 'Выберите группу.',
            'group.exists' => 'Выберите группу из списка.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Название', 'slug' => 'Slug', 'group' => 'Группа'];
    }

    public function group(): BlockGroup
    {
        return BlockGroup::query()->where('public_id', $this->string('group')->toString())->firstOrFail();
    }
}
