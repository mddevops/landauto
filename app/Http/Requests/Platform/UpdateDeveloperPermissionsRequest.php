<?php

namespace App\Http\Requests\Platform;

use App\Enums\DeveloperPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The full selected set of Developer creator permissions; an empty list is a valid zero-permission
 * state. Only keys of the `DeveloperPermission` catalog are accepted.
 */
class UpdateDeveloperPermissionsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array', 'max:'.count(DeveloperPermission::cases())],
            'permissions.*' => ['required', 'string', 'distinct', Rule::enum(DeveloperPermission::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'permissions.*.enum' => 'Неизвестное право разработчика.',
            'permissions.*.distinct' => 'Право разработчика указано дважды.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['permissions' => 'права разработчика', 'permissions.*' => 'право разработчика'];
    }

    /**
     * @return list<DeveloperPermission>
     */
    public function permissions(): array
    {
        /** @var list<string> $keys */
        $keys = $this->validated('permissions');

        return DeveloperPermission::fromKeys($keys);
    }
}
