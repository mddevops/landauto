<?php

namespace App\Http\Requests;

use App\Domains\CustomDomainAccess;
use App\Domains\CustomHostname;
use App\Models\Site;
use App\Support\DesignerScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteDomainRequest extends FormRequest
{
    public function authorize(DesignerScope $scope, CustomDomainAccess $access): bool
    {
        $scope->site($this->site());

        return $access->canManage($this->user(), $this->site());
    }

    public function site(): Site
    {
        /** @var Site $site */
        $site = $this->route('site');

        return $site;
    }

    protected function prepareForValidation(): void
    {
        $hostname = $this->input('hostname');

        if (is_string($hostname)) {
            $this->merge(['hostname' => CustomHostname::normalize($hostname)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hostname' => [
                'bail',
                'required',
                'string',
                'max:'.(CustomHostname::MAX_LENGTH + 1),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $problem = CustomHostname::problem((string) $value);

                    if ($problem !== null) {
                        $fail($problem);
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
            'hostname.required' => 'Укажите домен.',
            'hostname.string' => 'Укажите домен.',
            'hostname.max' => 'Домен слишком длинный.',
        ];
    }
}
