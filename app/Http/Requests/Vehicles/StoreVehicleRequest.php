<?php

namespace App\Http\Requests\Vehicles;

use App\Catalog\AutoCatalog;
use App\Enums\MediaAngle;
use App\Enums\WorkspacePermission;
use App\Models\Catalog\AutoSeries;
use App\Models\Site;
use App\Support\DesignerScope;
use App\Support\ImageUpload;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add-vehicle wizard (ADR-010): catalog Series, photos copied from the platform library or
 * uploaded, trims with optional prices (with and without discount), for the customer Catalog or
 * a Site.
 *
 * @phpstan-type OfferInput array{equipment: string, price_minor: int|null, rrp_minor: int|null}
 */
class StoreVehicleRequest extends FormRequest
{
    public const MAX_UPLOADS = 20;

    private ?AutoSeries $series = null;

    /**
     * Checked before validation so members without access never learn about catalog data.
     */
    public function authorize(): bool
    {
        $site = $this->route('site');

        if ($site instanceof Site) {
            app(DesignerScope::class)->site($site);

            return Gate::allows('editVehicles', $site);
        }

        return Gate::allows(WorkspacePermission::ManageWorkspaceVehicleLibrary->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'series' => ['required', 'string', 'max:48'],
            'custom_name' => ['nullable', 'string', 'max:120'],
            'colors' => ['nullable', 'array', 'max:30'],
            'colors.*' => ['string', 'size:26'],
            'gallery' => ['nullable', 'array', 'max:100'],
            'gallery.*' => ['string', 'size:26'],
            'catalog_image' => ['nullable', 'boolean'],
            'primary_angle' => ['nullable', Rule::enum(MediaAngle::class)],
            'uploads' => ['nullable', 'array', 'max:'.self::MAX_UPLOADS],
            'uploads.*' => ImageUpload::rules(),
            ...self::offerRules(),
            'collections' => ['nullable', 'array', 'max:50'],
            'collections.*' => ['string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'series.required' => 'Выберите кузов автомобиля.',
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
            'custom_name' => 'Название',
            'offers.*.price' => 'Цена со скидкой',
            'offers.*.rrp' => 'Цена без скидки',
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

            foreach ($this->uploads() as $index => $file) {
                ImageUpload::checkDecoded($validator, $file, "uploads.{$index}");
            }

            self::checkOffers($validator, $catalog, $this->series, $this->offerInput());
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public static function offerRules(): array
    {
        return [
            'offers' => ['nullable', 'array', 'max:300'],
            'offers.*.equipment' => ['required', 'string', 'max:64'],
            'offers.*.price' => ['nullable', 'string', 'max:32'],
            'offers.*.rrp' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $input
     */
    public static function checkOffers(Validator $validator, AutoCatalog $catalog, AutoSeries $series, array $input): void
    {
        $keys = array_map(fn (array $offer): string => (string) ($offer['equipment'] ?? ''), $input);

        if (count($keys) !== count(array_unique($keys))) {
            $validator->errors()->add('offers', 'Каждую комплектацию можно выбрать только один раз.');
        } elseif ($catalog->equipments([$series->id => $keys])->count() !== count($keys)) {
            $validator->errors()->add('offers', 'Выберите комплектации этого кузова. Обновите страницу.');
        }

        foreach ($input as $index => $offer) {
            $price = self::money($offer['price'] ?? null);
            $rrp = self::money($offer['rrp'] ?? null);

            if ($price === false) {
                $validator->errors()->add("offers.{$index}.price", 'Укажите цену в рублях, например 1 850 000.');
            }

            if ($rrp === false) {
                $validator->errors()->add("offers.{$index}.rrp", 'Укажите цену в рублях, например 1 990 000.');
            }

            if (is_int($price) && is_int($rrp) && $rrp < $price) {
                $validator->errors()->add("offers.{$index}.rrp", 'Цена без скидки должна быть не меньше цены со скидкой.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $input
     * @return list<OfferInput>
     */
    public static function parseOffers(array $input): array
    {
        return array_map(fn (array $offer): array => [
            'equipment' => (string) $offer['equipment'],
            'price_minor' => self::money($offer['price'] ?? null) ?: null,
            'rrp_minor' => self::money($offer['rrp'] ?? null) ?: null,
        ], $input);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function offerList(mixed $offers): array
    {
        return is_array($offers) ? array_values(array_filter($offers, 'is_array')) : [];
    }

    public function series(): AutoSeries
    {
        return $this->series ?? abort(422);
    }

    /**
     * @return array{series: string, custom_name: string|null, colors: list<string>, gallery: list<string>, catalog_image: bool, primary_angle: MediaAngle|null, uploads: list<UploadedFile>, offers: list<OfferInput>}
     */
    public function draft(): array
    {
        return [
            'series' => (string) $this->series()->ref(),
            'custom_name' => $this->customName(),
            'colors' => $this->strings('colors'),
            'gallery' => $this->strings('gallery'),
            'catalog_image' => $this->boolean('catalog_image'),
            'primary_angle' => $this->primaryAngle(),
            'uploads' => $this->uploads(),
            'offers' => $this->offers(),
        ];
    }

    /**
     * @return list<OfferInput>
     */
    public function offers(): array
    {
        return self::parseOffers($this->offerInput());
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        $values = $this->input($key, []);

        return is_array($values) ? array_values(array_unique(array_map('strval', $values))) : [];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array
    {
        $files = $this->file('uploads');

        return is_array($files) ? array_values($files) : [];
    }

    public function primaryAngle(): ?MediaAngle
    {
        return MediaAngle::tryFrom($this->string('primary_angle')->toString());
    }

    public function customName(): ?string
    {
        $name = trim($this->string('custom_name')->toString());

        return $name === '' ? null : $name;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function offerInput(): array
    {
        return self::offerList($this->input('offers', []));
    }

    /**
     * Blank → null, invalid → false.
     */
    private static function money(mixed $value): int|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Money::parse($value) ?? false;
    }
}
