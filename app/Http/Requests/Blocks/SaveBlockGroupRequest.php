<?php

namespace App\Http\Requests\Blocks;

use App\Enums\BlockCategory;
use App\Models\BlockGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Block Group name and the visual type of its Blocks; names are unique across the platform.
 */
class SaveBlockGroupRequest extends FormRequest
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
        $group = $this->route('group');

        return [
            'name' => [
                'required',
                'string',
                'max:'.BlockGroup::NAME_MAX,
                Rule::unique('block_groups', 'name')->ignore(is_string($group) ? $group : null, 'public_id'),
            ],
            'kind' => ['required', Rule::enum(BlockCategory::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Группа с таким названием уже есть.',
            'kind.required' => 'Выберите тип блоков.',
            'kind.enum' => 'Выберите тип блоков из списка.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Название', 'kind' => 'Тип блоков'];
    }

    public function kind(): BlockCategory
    {
        return BlockCategory::from($this->string('kind')->toString());
    }
}
