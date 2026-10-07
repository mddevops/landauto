<?php

namespace App\Http\Requests\Blocks;

use App\Enums\BlockCategory;
use App\Models\BlockDefinition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name and category are editable; the slug and ownership never change after creation.
 */
class UpdateBlockDefinitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.BlockDefinition::NAME_MAX],
            'category' => ['required', Rule::enum(BlockCategory::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.required' => 'Выберите категорию.',
            'category.enum' => 'Выберите категорию из списка.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Название', 'category' => 'Категория'];
    }

    public function category(): BlockCategory
    {
        return BlockCategory::from($this->string('category')->toString());
    }
}
