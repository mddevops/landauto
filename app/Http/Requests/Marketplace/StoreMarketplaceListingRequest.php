<?php

namespace App\Http\Requests\Marketplace;

use App\Enums\MarketplaceProductType;
use App\Models\MarketplaceListing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * A new listing names its product by type + public ID only. The owner is derived on the server from
 * that product; `developer_profile_id`, `owner_scope`, status and prices are never read.
 */
class StoreMarketplaceListingRequest extends UpdateMarketplaceListingRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'slug' => is_string($this->input('slug')) ? trim($this->input('slug')) : $this->input('slug'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_type' => ['required', Rule::enum(MarketplaceProductType::class)],
            'product' => ['required', 'string', 'max:26'],
            'slug' => [
                'required', 'string', 'min:'.MarketplaceListing::SLUG_MIN, 'max:'.MarketplaceListing::SLUG_MAX,
                'regex:'.MarketplaceListing::SLUG_PATTERN, Rule::unique('marketplace_listings', 'slug'),
            ],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_type.required' => 'Выберите тип: блок или шаблон.',
            'product_type.enum' => 'Выберите тип: блок или шаблон.',
            'product.required' => 'Выберите продукт.',
            'slug.regex' => 'Slug может содержать только строчные латинские буквы, цифры и одиночные дефисы.',
            'slug.unique' => 'Этот slug уже используется другой карточкой.',
            ...parent::messages(),
        ];
    }

    public function productType(): MarketplaceProductType
    {
        return MarketplaceProductType::from($this->string('product_type')->toString());
    }

    public function product(): string
    {
        return $this->string('product')->toString();
    }

    public function slug(): string
    {
        return $this->string('slug')->toString();
    }
}
