<?php

namespace App\Http\Requests\Blocks;

use App\Models\BlockDefinition;
use App\Models\BlockGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Name and group are editable; the slug and ownership never change after creation.
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
            'group' => ['required', 'string', Rule::exists('block_groups', 'public_id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'group.required' => 'Выберите группу.',
            'group.exists' => 'Выберите группу из списка.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Название', 'group' => 'Группа'];
    }

    public function group(): BlockGroup
    {
        return BlockGroup::query()->where('public_id', $this->string('group')->toString())->firstOrFail();
    }
}
