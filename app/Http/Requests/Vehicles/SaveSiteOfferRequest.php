<?php

namespace App\Http\Requests\Vehicles;

use App\Catalog\CatalogReferences;
use App\Enums\BenefitType;
use App\Enums\OfferAvailability;
use App\Models\Site;
use App\Models\SiteOffer;
use App\Models\SiteVehicle;
use App\Support\DesignerScope;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Offer form: human decimal prices are parsed into minor units on the server (ADR-004) and the
 * Equipment must belong to the vehicle's Series (D-104).
 */
class SaveSiteOfferRequest extends FormRequest
{
    public const MAX_BENEFITS = 10;

    public function authorize(): bool
    {
        $site = $this->site();
        $scope = app(DesignerScope::class);
        $offer = $this->route('offer');
        $offer instanceof SiteOffer ? $scope->offer($site, $offer) : $scope->vehicle($site, $this->vehicle());

        return Gate::allows('editPrices', $site);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'equipment' => ['required', 'string'],
            'price' => ['required', 'string', 'max:32'],
            'rrp' => ['nullable', 'string', 'max:32'],
            'availability' => ['nullable', Rule::enum(OfferAvailability::class)],
            'badge' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'benefits' => ['nullable', 'array', 'max:'.self::MAX_BENEFITS],
            'benefits.*.type' => ['required', Rule::enum(BenefitType::class)],
            'benefits.*.amount' => ['required', 'string', 'max:32'],
            'benefits.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (Money::parse($this->string('price')->toString()) === null) {
                $validator->errors()->add('price', 'Укажите цену в рублях, например 1 850 000.');
            }

            if ($this->filled('rrp') && Money::parse($this->string('rrp')->toString()) === null) {
                $validator->errors()->add('rrp', 'Укажите цену без скидки в рублях, например 1 990 000.');
            }

            foreach ($this->benefitInput() as $index => $benefit) {
                $amount = Money::parse((string) $benefit['amount']);

                if ($amount === null || $amount < 1) {
                    $validator->errors()->add("benefits.{$index}.amount", 'Укажите сумму выгоды в рублях.');
                }
            }

            $this->validateEquipment($validator);
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'equipment' => 'Комплектация',
            'price' => 'Цена',
            'rrp' => 'Цена без скидки',
            'availability' => 'Наличие',
            'badge' => 'Метка',
            'status' => 'Показ на сайте',
            'sort_order' => 'Сортировка',
            'benefits' => 'Выгоды',
            'benefits.*.type' => 'Тип выгоды',
            'benefits.*.amount' => 'Сумма выгоды',
            'benefits.*.label' => 'Подпись выгоды',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function offerAttributes(): array
    {
        $badge = trim($this->string('badge')->toString());

        return [
            'catalog_equipment_public_id' => $this->string('equipment')->toString(),
            'price_minor' => Money::parse($this->string('price')->toString()),
            'rrp_minor' => $this->filled('rrp') ? Money::parse($this->string('rrp')->toString()) : null,
            'currency' => Money::DEFAULT_CURRENCY,
            'availability' => $this->input('availability') ?: null,
            'badge' => $badge === '' ? null : $badge,
            'status' => $this->boolean('status'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }

    /**
     * @return list<array{type: BenefitType, amount_minor: int, label: string|null}>
     */
    public function benefits(): array
    {
        return array_map(function (array $benefit): array {
            $label = trim((string) ($benefit['label'] ?? ''));

            return [
                'type' => BenefitType::from((string) $benefit['type']),
                'amount_minor' => (int) Money::parse((string) $benefit['amount']),
                'label' => $label === '' ? null : $label,
            ];
        }, $this->benefitInput());
    }

    public function site(): Site
    {
        $site = $this->route('site');
        abort_unless($site instanceof Site, 404);

        return $site;
    }

    public function vehicle(): SiteVehicle
    {
        $offer = $this->route('offer');

        if ($offer instanceof SiteOffer) {
            return $offer->vehicle;
        }

        $vehicle = $this->route('vehicle');
        abort_unless($vehicle instanceof SiteVehicle, 404);

        return $vehicle;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function benefitInput(): array
    {
        $benefits = $this->input('benefits', []);

        return is_array($benefits) ? array_values(array_filter($benefits, 'is_array')) : [];
    }

    private function validateEquipment(Validator $validator): void
    {
        $publicId = $this->string('equipment')->toString();
        $offer = $this->route('offer');

        if ($offer instanceof SiteOffer && $offer->catalog_equipment_public_id === $publicId) {
            return;
        }

        $references = app(CatalogReferences::class);
        $equipment = $references->equipment($publicId, availableOnly: true);

        if ($equipment === null || ! $references->equipmentBelongsToSeries($equipment, $this->vehicle()->catalog_series_public_id)) {
            $validator->errors()->add('equipment', 'Выберите комплектацию этой серии.');
        }
    }
}
