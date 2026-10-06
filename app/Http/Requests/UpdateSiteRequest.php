<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSiteRequest extends FormRequest
{
    public function authorize(DesignerScope $scope): bool
    {
        /** @var Site $site */
        $site = $this->route('site');
        $scope->site($site);

        return Gate::allows('update', $site);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название сайта.',
            'name.string' => 'Название сайта должно быть строкой.',
            'name.max' => 'Название сайта не должно превышать 255 символов.',
            'name.not_regex' => 'Название сайта не должно содержать служебных символов.',
        ];
    }
}
