<?php

namespace App\Http\Requests\Marketplace;

use App\Models\MarketplaceListing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editable marketing text of a Marketplace Listing: plain text only, rendered escaped. The slug,
 * product, owner, status and every commercial field are not accepted here.
 */
class UpdateMarketplaceListingRequest extends FormRequest
{
    private const NO_MARKUP = 'not_regex:/<\s*[a-z!\/?]/i';

    protected function prepareForValidation(): void
    {
        $description = is_string($this->input('description')) ? trim(str_replace("\r\n", "\n", $this->input('description'))) : $this->input('description');

        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'description' => $description === '' ? null : $description,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.MarketplaceListing::TITLE_MAX, self::NO_MARKUP],
            'description' => ['nullable', 'string', 'max:'.MarketplaceListing::DESCRIPTION_MAX, self::NO_MARKUP],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.not_regex' => 'Название — обычный текст, без HTML-разметки.',
            'description.not_regex' => 'Описание — обычный текст, без HTML-разметки.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['title' => 'Название', 'description' => 'Описание', 'slug' => 'Slug', 'product' => 'Продукт', 'product_type' => 'Тип'];
    }

    public function title(): string
    {
        return $this->string('title')->toString();
    }

    public function description(): ?string
    {
        $description = $this->input('description');

        return is_string($description) ? $description : null;
    }
}
