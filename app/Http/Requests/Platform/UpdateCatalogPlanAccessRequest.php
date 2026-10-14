<?php

namespace App\Http\Requests\Platform;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogPlanAccessRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_keys' => ['present', 'array', 'max:100'],
            'plan_keys.*' => ['required', 'string', 'distinct', Rule::exists('plans', 'key')],
        ];
    }

    /** @return list<string> */
    public function planKeys(): array
    {
        return array_values($this->array('plan_keys'));
    }
}
