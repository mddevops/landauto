<?php

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:'.Workspace::NAME_MAX_LENGTH,
                'not_regex:/[\x00-\x1F\x7F<>]/u',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название пространства.',
            'name.string' => 'Название пространства должно быть строкой.',
            'name.max' => 'Название пространства не должно превышать '.Workspace::NAME_MAX_LENGTH.' символов.',
            'name.not_regex' => 'Название пространства должно быть обычным текстом без служебных символов и HTML.',
        ];
    }
}
