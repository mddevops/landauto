<?php

namespace App\Http\Requests\Blocks;

use App\Enums\CatalogAccessMode;
use App\Enums\Entitlement;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Catalog access of a Block (D-079). The price arrives as a human decimal string and is parsed into
 * minor units on the server (ADR-004); the browser never sends authoritative minor units.
 */
class UpdateBlockAccessRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(CatalogAccessMode::class)],
            'entitlement' => [
                'nullable',
                'required_if:mode,'.CatalogAccessMode::Entitlement->value,
                Rule::in(array_map(fn (Entitlement $entitlement): string => $entitlement->value, Entitlement::catalogGates())),
            ],
            'price' => ['nullable', 'required_if:mode,'.CatalogAccessMode::Paid->value, 'string', 'max:32'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['mode', 'price'])) {
                return;
            }

            if ($this->mode() === CatalogAccessMode::Paid && ($this->priceMinor() ?? 0) <= 0) {
                $validator->errors()->add('price', 'Укажите цену больше нуля, например 1500 или 1500,50.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mode.required' => 'Выберите режим доступа.',
            'mode.enum' => 'Выберите режим доступа из списка.',
            'entitlement.required_if' => 'Выберите опцию тарифа.',
            'entitlement.in' => 'Выберите опцию тарифа из списка.',
            'price.required_if' => 'Укажите цену для платного блока.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['mode' => 'Режим доступа', 'entitlement' => 'Опция тарифа', 'price' => 'Цена'];
    }

    public function mode(): CatalogAccessMode
    {
        return CatalogAccessMode::from($this->string('mode')->toString());
    }

    public function entitlement(): ?Entitlement
    {
        return $this->mode() === CatalogAccessMode::Entitlement ? Entitlement::from($this->string('entitlement')->toString()) : null;
    }

    public function priceMinor(): ?int
    {
        return $this->mode() === CatalogAccessMode::Paid ? Money::parse($this->string('price')->toString()) : null;
    }
}
