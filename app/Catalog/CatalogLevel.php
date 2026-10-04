<?php

namespace App\Catalog;

use App\Enums\Catalog\DriveType;
use App\Enums\Catalog\EngineType;
use App\Enums\Catalog\TransmissionType;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoSeries;
use App\Models\Catalog\CatalogModel;
use Illuminate\Validation\Rule;

/**
 * Catalog V2 hierarchy levels: Mark → Model → Generation → Series → Modification → Equipment.
 */
enum CatalogLevel: string
{
    case Marks = 'marks';
    case Models = 'models';
    case Generations = 'generations';
    case Series = 'series';
    case Modifications = 'modifications';
    case Equipments = 'equipments';

    private const URL_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * @return class-string<CatalogModel>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Marks => AutoMark::class,
            self::Models => AutoModel::class,
            self::Generations => AutoGeneration::class,
            self::Series => AutoSeries::class,
            self::Modifications => AutoModification::class,
            self::Equipments => AutoEquipment::class,
        };
    }

    public function parent(): ?self
    {
        return match ($this) {
            self::Marks => null,
            self::Models => self::Marks,
            self::Generations => self::Models,
            self::Series => self::Generations,
            self::Modifications => self::Series,
            self::Equipments => self::Modifications,
        };
    }

    public function child(): ?self
    {
        foreach (self::cases() as $level) {
            if ($level->parent() === $this) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Foreign key column pointing at the parent level.
     */
    public function parentKey(): ?string
    {
        return match ($this) {
            self::Marks => null,
            self::Models => 'mark_id',
            self::Generations => 'model_id',
            self::Series => 'generation_id',
            self::Modifications => 'series_id',
            self::Equipments => 'modification_id',
        };
    }

    /**
     * Query-string key used by the cascading catalog browser.
     */
    public function selectionKey(): string
    {
        return match ($this) {
            self::Marks => 'mark',
            self::Models => 'model',
            self::Generations => 'generation',
            self::Series => 'series',
            self::Modifications => 'modification',
            self::Equipments => 'equipment',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Marks => 'Марки',
            self::Models => 'Модели',
            self::Generations => 'Поколения',
            self::Series => 'Серии',
            self::Modifications => 'Модификации',
            self::Equipments => 'Комплектации',
        };
    }

    public function find(string $publicId): ?CatalogModel
    {
        return $this->modelClass()::query()->where('public_id', $publicId)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(?CatalogModel $parent, ?CatalogModel $entry): array
    {
        $common = [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
        $years = [
            'year_from' => ['nullable', 'integer', 'min:1886', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1886', 'max:2100', 'gte:year_from'],
        ];
        $url = fn (string $table) => [
            'required', 'string', 'max:160', 'regex:'.self::URL_PATTERN,
            Rule::unique("catalog.{$table}", 'url')
                ->when($this->parentKey() !== null, fn ($rule) => $rule->where($this->parentKey(), $parent?->getKey()))
                ->ignore($entry?->getKey()),
        ];

        return match ($this) {
            self::Marks => [
                ...$common,
                'name_ru' => ['nullable', 'string', 'max:255'],
                'url' => $url('auto_marks'),
                'logo_min' => ['nullable', 'string', 'max:1024'],
                'logo_big' => ['nullable', 'string', 'max:1024'],
                'country' => ['nullable', 'string', 'max:100'],
            ],
            self::Models => [
                ...$common,
                ...$years,
                'name_ru' => ['nullable', 'string', 'max:255'],
                'url' => $url('auto_models'),
                'class' => ['nullable', 'string', 'max:32'],
                'group' => ['nullable', 'string', 'size:26'],
            ],
            self::Generations => [...$common, ...$years, 'url' => $url('auto_generations')],
            self::Series => [
                ...$common,
                'url' => $url('auto_series'),
                'image' => ['nullable', 'string', 'max:1024'],
            ],
            self::Modifications => [
                ...$common,
                'engine_volume' => ['nullable', 'integer', 'min:1', 'max:30000'],
                'engine_power' => ['nullable', 'regex:/^\d{1,6}(\.\d{1,2})?$/'],
                'engine' => ['nullable', Rule::enum(EngineType::class)],
                'transmission' => ['nullable', Rule::enum(TransmissionType::class)],
                'drive' => ['nullable', Rule::enum(DriveType::class)],
                'consumption_100_km' => ['nullable', 'regex:/^\d{1,5}(\.\d{1,3})?$/'],
                'acceleration_0_100' => ['nullable', 'regex:/^\d{1,4}(\.\d{1,2})?$/'],
            ],
            self::Equipments => $common,
        };
    }

    /**
     * Editable fields for the platform UI (never numeric IDs).
     *
     * @return array<string, mixed>
     */
    public function present(CatalogModel $entry): array
    {
        $base = [
            'public_id' => $entry->public_id,
            'name' => (string) $entry->getAttribute('name'),
            'status' => (bool) $entry->getAttribute('status'),
            'sort_order' => (int) $entry->getAttribute('sort_order'),
        ];

        return match (true) {
            $entry instanceof AutoMark => [...$base, ...$entry->only(['name_ru', 'url', 'logo_min', 'logo_big', 'country'])],
            $entry instanceof AutoModel => [
                ...$base,
                ...$entry->only(['name_ru', 'url', 'class', 'year_from', 'year_to']),
                'group' => $entry->parent?->public_id,
            ],
            $entry instanceof AutoGeneration => [...$base, ...$entry->only(['url', 'year_from', 'year_to'])],
            $entry instanceof AutoSeries => [...$base, ...$entry->only(['url', 'image'])],
            $entry instanceof AutoModification => [...$base, ...$entry->only([
                'engine_volume', 'engine_power', 'engine', 'transmission', 'drive', 'consumption_100_km', 'acceleration_0_100',
            ])],
            default => $base,
        };
    }
}
