<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Support\DesignerScope;
use App\Support\SiteSubdomain;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSiteSubdomainRequest extends FormRequest
{
    public function authorize(DesignerScope $scope): bool
    {
        $scope->site($this->site());

        return Gate::allows('manageDomains', $this->site());
    }

    public function site(): Site
    {
        /** @var Site $site */
        $site = $this->route('site');

        return $site;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('subdomain'))) {
            $this->merge(['subdomain' => strtolower(trim($this->input('subdomain')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subdomain' => [
                'required',
                'string',
                'max:'.SiteSubdomain::MAX_LENGTH,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    if (! SiteSubdomain::isWellFormed($value)) {
                        $fail('Используйте строчные латинские буквы, цифры и дефис; дефис не может быть первым или последним.');
                    } elseif (SiteSubdomain::isReserved($value)) {
                        $fail('Этот адрес зарезервирован Landflow. Выберите другой.');
                    } elseif (SiteSubdomain::isTaken($value, $this->site())) {
                        $fail('Этот адрес уже занят. Выберите другой.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.required' => 'Укажите адрес сайта.',
            'subdomain.string' => 'Укажите адрес сайта.',
            'subdomain.max' => 'Адрес сайта не должен превышать 63 символа.',
        ];
    }
}
