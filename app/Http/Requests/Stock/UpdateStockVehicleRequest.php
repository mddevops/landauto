<?php

namespace App\Http\Requests\Stock;

use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockPtsDocumentType;
use App\Enums\StockVehicleStatus;
use App\Models\StockVehicle;
use App\Stock\StockAccess;
use App\Stock\StockColors;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Edit a Stock listing in the Workspace cabinet (ADR-012).
 */
class UpdateStockVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        app(StockAccess::class)->authorize($user);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxYear = (int) date('Y') + 1;

        return [
            'year' => ['required', 'integer', 'min:1950', 'max:'.$maxYear],
            'body_name' => ['nullable', 'string', 'max:120'],
            'color_name' => ['required', 'string', 'max:100'],
            'color_swatch_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'owners_count' => ['nullable', 'integer', 'min:1', 'max:20'],
            'vin' => ['nullable', 'string', 'max:17', 'regex:/^[A-HJ-NPR-Z0-9]*$/i'],
            'color_id' => ['nullable', 'string', 'max:16'],
            'availability' => ['required', Rule::enum(StockAvailability::class)],
            'price' => ['nullable', 'string', 'max:32'],
            'inner_id' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::enum(StockVehicleStatus::class)],
            'pts_document_type' => ['nullable', Rule::enum(StockPtsDocumentType::class)],
            'purchased_year' => ['nullable', 'integer', 'min:1950', 'max:'.$maxYear],
            'purchased_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'on_warranty' => ['nullable', 'boolean'],
            'not_customs_cleared' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'color_name' => 'Цвет',
            'mileage_km' => 'Пробег',
            'owners_count' => 'Владельцы',
            'vin' => 'VIN',
            'price' => 'Цена',
            'inner_id' => 'Складской номер',
            'status' => 'Статус',
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

            $vehicle = $this->stockVehicle();
            $mileage = $this->input('mileage_km');
            $owners = $this->input('owners_count');
            $status = StockVehicleStatus::from($this->string('status')->toString());

            if ($vehicle->condition === StockCondition::New) {
                if ($mileage !== null && $mileage !== '') {
                    $validator->errors()->add('mileage_km', 'У нового автомобиля не указывают пробег.');
                }
                if ($owners !== null && $owners !== '') {
                    $validator->errors()->add('owners_count', 'У нового автомобиля не указывают число владельцев.');
                }
            }

            if ($vehicle->condition === StockCondition::Used && $status === StockVehicleStatus::Ready) {
                if ($mileage === null || $mileage === '') {
                    $validator->errors()->add('mileage_km', 'Укажите пробег в километрах.');
                }
                if ($owners === null || $owners === '') {
                    $validator->errors()->add('owners_count', 'Укажите количество владельцев.');
                }
            }

            $colorId = trim($this->string('color_id')->toString());

            if ($colorId !== '' && StockColors::find($colorId) === null) {
                $validator->errors()->add('color_name', 'Выберите цвет из списка.');
            }

            $price = $this->money($this->input('price'));

            if ($price === false) {
                $validator->errors()->add('price', 'Укажите цену в рублях, например 1 850 000.');
            }

            $innerId = trim($this->string('inner_id')->toString());

            if ($innerId !== '') {
                $exists = $vehicle->workspace->stockVehicles()
                    ->where('inner_id', $innerId)
                    ->whereKeyNot($vehicle->getKey())
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('inner_id', 'Такой складской номер уже есть в этом пространстве.');
                }
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $price = $this->money($this->input('price'));
        $innerId = trim($this->string('inner_id')->toString());
        $vehicle = $this->stockVehicle();
        $colorId = trim($this->string('color_id')->toString());
        $palette = $colorId === '' ? null : StockColors::find($colorId);
        $isUsed = $vehicle->condition === StockCondition::Used;

        return [
            'year' => $this->integer('year'),
            'body_name' => $this->nullableString('body_name'),
            'color_name' => $palette['name'] ?? trim($this->string('color_name')->toString()),
            'color_swatch_hex' => $palette['hex'] ?? $this->nullableString('color_swatch_hex'),
            'mileage_km' => $isUsed && $this->filled('mileage_km') ? $this->integer('mileage_km') : null,
            'owners_count' => $isUsed && $this->filled('owners_count') ? $this->integer('owners_count') : null,
            'vin' => $isUsed
                ? ($this->filled('vin') ? $this->nullableString('vin') : $vehicle->vin)
                : null,
            'availability' => StockAvailability::from($this->string('availability')->toString()),
            'price_minor' => is_int($price) ? $price : null,
            'inner_id' => $innerId === '' ? null : $innerId,
            'status' => StockVehicleStatus::from($this->string('status')->toString()),
            'pts_document_type' => StockPtsDocumentType::tryFrom($this->string('pts_document_type')->toString()),
            'purchased_year' => $this->filled('purchased_year') ? $this->integer('purchased_year') : null,
            'purchased_month' => $this->filled('purchased_month') ? $this->integer('purchased_month') : null,
            'on_warranty' => $this->boolean('on_warranty'),
            'not_customs_cleared' => $this->boolean('not_customs_cleared'),
            'description' => $this->nullableString('description'),
        ];
    }

    public function stockVehicle(): StockVehicle
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof StockVehicle ? $vehicle : abort(404);
    }

    private function nullableString(string $key): ?string
    {
        $value = trim($this->string($key)->toString());

        return $value === '' ? null : $value;
    }

    private function money(mixed $value): int|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Money::parse($value) ?? false;
    }
}
