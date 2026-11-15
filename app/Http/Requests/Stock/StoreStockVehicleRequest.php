<?php

namespace App\Http\Requests\Stock;

use App\Catalog\AutoCatalog;
use App\Enums\StockAvailability;
use App\Enums\StockCondition;
use App\Enums\StockPtsDocumentType;
use App\Enums\StockVehicleStatus;
use App\Models\Catalog\AutoSeries;
use App\Stock\StockAccess;
use App\Stock\StockColors;
use App\Support\ImageUpload;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Stock add wizard (ADR-012): catalog Series / optional Equipment, listing fields, optional uploads.
 */
class StoreStockVehicleRequest extends FormRequest
{
    public const MAX_UPLOADS = 40;

    private ?AutoSeries $series = null;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // Match cabinet behaviour: missing entitlement / permission → 404, not 403.
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
            'condition' => ['required', Rule::enum(StockCondition::class)],
            'series' => ['required', 'string', 'max:48'],
            'equipment' => ['nullable', 'string', 'max:64'],
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
            'photos_later' => ['nullable', 'boolean'],
            'pts_document_type' => ['nullable', Rule::enum(StockPtsDocumentType::class)],
            'purchased_year' => ['nullable', 'integer', 'min:1950', 'max:'.$maxYear],
            'purchased_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'on_warranty' => ['nullable', 'boolean'],
            'not_customs_cleared' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'uploads' => ['nullable', 'array', 'max:'.self::MAX_UPLOADS],
            'uploads.*' => ImageUpload::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'condition.required' => 'Выберите новый автомобиль или с пробегом.',
            'series.required' => 'Выберите кузов автомобиля.',
            'year.required' => 'Укажите год выпуска.',
            'color_name.required' => 'Укажите цвет.',
            'availability.required' => 'Укажите наличие.',
            'uploads.max' => 'Можно загрузить не больше '.self::MAX_UPLOADS.' фото.',
            ...ImageUpload::messages('uploads.*'),
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

            $catalog = app(AutoCatalog::class);
            $this->series = $catalog->series($this->string('series')->toString());

            if ($this->series === null) {
                $validator->errors()->add('series', 'Кузов не найден в каталоге. Обновите страницу и выберите снова.');

                return;
            }

            $condition = StockCondition::from($this->string('condition')->toString());
            $mileage = $this->input('mileage_km');

            $owners = $this->input('owners_count');

            if ($condition === StockCondition::New) {
                if ($mileage !== null && $mileage !== '') {
                    $validator->errors()->add('mileage_km', 'У нового автомобиля не указывают пробег.');
                }
                if ($owners !== null && $owners !== '') {
                    $validator->errors()->add('owners_count', 'У нового автомобиля не указывают число владельцев.');
                }
            }

            if ($condition === StockCondition::Used) {
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

            $equipment = trim($this->string('equipment')->toString());

            if ($equipment !== '' && $catalog->equipment($this->series, $equipment) === null) {
                $validator->errors()->add('equipment', 'Выберите комплектацию этого кузова.');
            }

            $price = $this->money($this->input('price'));

            if ($price === false) {
                $validator->errors()->add('price', 'Укажите цену в рублях, например 1 850 000.');
            }

            $innerId = trim($this->string('inner_id')->toString());

            if ($innerId !== '') {
                $workspace = app(StockAccess::class)->authorize($this->user());
                $exists = $workspace->stockVehicles()->where('inner_id', $innerId)->exists();

                if ($exists) {
                    $validator->errors()->add('inner_id', 'Такой складской номер уже есть в этом пространстве.');
                }
            }

            foreach ($this->uploads() as $index => $file) {
                ImageUpload::checkDecoded($validator, $file, "uploads.{$index}");
            }
        }];
    }

    /**
     * @return array{
     *   condition: StockCondition,
     *   series: string,
     *   equipment: string|null,
     *   year: int,
     *   body_name: string|null,
     *   color_name: string,
     *   color_swatch_hex: string|null,
     *   mileage_km: int|null,
     *   owners_count: int|null,
     *   vin: string|null,
     *   availability: StockAvailability,
     *   price_minor: int|null,
     *   inner_id: string|null,
     *   status: StockVehicleStatus,
     *   pts_document_type: StockPtsDocumentType|null,
     *   purchased_year: int|null,
     *   purchased_month: int|null,
     *   on_warranty: bool,
     *   not_customs_cleared: bool,
     *   description: string|null,
     *   uploads: list<UploadedFile>
     * }
     */
    public function draft(): array
    {
        $series = $this->series();
        $equipment = trim($this->string('equipment')->toString());
        $photosLater = $this->boolean('photos_later');
        $uploads = $this->uploads();
        $price = $this->money($this->input('price'));
        $condition = StockCondition::from($this->string('condition')->toString());
        $colorId = trim($this->string('color_id')->toString());
        $palette = $colorId === '' ? null : StockColors::find($colorId);

        return [
            'condition' => $condition,
            'series' => (string) $series->ref(),
            'equipment' => $equipment === '' ? null : $equipment,
            'year' => $this->integer('year'),
            'body_name' => $this->nullableString('body_name') ?? $series->name,
            'color_name' => $palette['name'] ?? trim($this->string('color_name')->toString()),
            'color_swatch_hex' => $palette['hex'] ?? $this->nullableString('color_swatch_hex'),
            'mileage_km' => $condition === StockCondition::Used && $this->filled('mileage_km') ? $this->integer('mileage_km') : null,
            'owners_count' => $condition === StockCondition::Used && $this->filled('owners_count') ? $this->integer('owners_count') : null,
            'vin' => $condition === StockCondition::Used ? $this->nullableString('vin') : null,
            'availability' => StockAvailability::from($this->string('availability')->toString()),
            'price_minor' => is_int($price) ? $price : null,
            'inner_id' => $this->nullableString('inner_id'),
            'status' => ($photosLater && $uploads === []) ? StockVehicleStatus::Draft : StockVehicleStatus::Ready,
            'pts_document_type' => StockPtsDocumentType::tryFrom($this->string('pts_document_type')->toString()),
            'purchased_year' => $this->filled('purchased_year') ? $this->integer('purchased_year') : null,
            'purchased_month' => $this->filled('purchased_month') ? $this->integer('purchased_month') : null,
            'on_warranty' => $this->boolean('on_warranty'),
            'not_customs_cleared' => $this->boolean('not_customs_cleared'),
            'description' => $this->nullableString('description'),
            'uploads' => $uploads,
        ];
    }

    public function series(): AutoSeries
    {
        return $this->series ?? abort(422);
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array
    {
        $files = $this->file('uploads');

        return is_array($files) ? array_values($files) : [];
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
