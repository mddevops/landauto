<?php

namespace App\Http\Requests\Blocks;

use App\Enums\CatalogAccessMode;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Catalog access of a Block or Template (D-121). A paid item offers a Site license, a Workspace
 * license or both; prices arrive as human decimal strings and are parsed into minor units on the
 * server (ADR-004); the browser never sends authoritative minor units.
 */
class UpdateBlockAccessRequest extends FormRequest
{
    private const PRICES = ['site_price', 'workspace_price'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(CatalogAccessMode::class)],
            'site_price' => ['nullable', 'string', 'max:32'],
            'workspace_price' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['mode', ...self::PRICES]) || $this->mode() !== CatalogAccessMode::Paid) {
                return;
            }

            if (! $this->filled('site_price') && ! $this->filled('workspace_price')) {
                $validator->errors()->add('site_price', 'Укажите цену лицензии на сайт, на всё пространство или обе.');

                return;
            }

            foreach (self::PRICES as $field) {
                if ($this->filled($field) && ($this->priceMinor($field) ?? 0) <= 0) {
                    $validator->errors()->add($field, 'Укажите цену больше нуля, например 1500 или 1500,50.');
                }
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mode' => 'Режим доступа',
            'site_price' => 'Лицензия на 1 сайт',
            'workspace_price' => 'Лицензия на всё пространство',
        ];
    }

    public function mode(): CatalogAccessMode
    {
        return CatalogAccessMode::from($this->string('mode')->toString());
    }

    public function entitlement(): null
    {
        return null;
    }

    public function sitePriceMinor(): ?int
    {
        return $this->priceMinor('site_price');
    }

    public function workspacePriceMinor(): ?int
    {
        return $this->priceMinor('workspace_price');
    }

    private function priceMinor(string $field): ?int
    {
        return $this->mode() === CatalogAccessMode::Paid && $this->filled($field) ? Money::parse($this->string($field)->toString()) : null;
    }
}
