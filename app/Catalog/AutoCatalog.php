<?php

namespace App\Catalog;

use App\Models\Catalog\AutoCharacteristicValue;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoModification;
use App\Models\Catalog\AutoOptionValue;
use App\Models\Catalog\AutoSeries;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Read-only access to the auto.ru catalog (ADR-010). Customer rows hold only string references:
 * a Series ref (`{generation external id}:{series external id}`) and an Equipment `external_key`
 * inside that Series. Numeric catalog ids never leave the server.
 *
 * @phpstan-type Section array{group: string, items: list<array{label: string, value: string}>}
 * @phpstan-type OptionSection array{group: string, items: list<array{name: string, availability: string}>}
 */
final class AutoCatalog
{
    private const REF_PATTERN = '/^(\d{1,20}):(\d{1,20})$/';

    public const IMAGES_DISK = 'auto_catalog';

    /** Catalog image paths as stored by the importer, e.g. `serie/2/G73Lbrv.jpg`, `mark_small/bmw.webp`. */
    public const IMAGE_PATH_PATTERN = '#^(?:mark_small|mark_big|mark_dark|serie)/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:jpe?g|png|webp|svg)$#i';

    private const DRIVES = ['Передний', 'Задний', 'Полный'];

    private const FACT_CHARACTERISTICS = [
        'volume' => 'Объем двигателя',
        'power' => 'Максимальная мощность',
        'engine' => 'Тип двигателя',
        'transmission' => 'Коробка передач',
        'drive' => 'Тип привода',
        'acceleration' => 'Разгон до 100 км/ч',
        'consumption' => 'Расход топлива, город/трасса/смешанный',
    ];

    private const ENGINES = ['Бензиновый' => 'бензин', 'Дизельный' => 'дизель', 'Электро' => 'электро', 'Гибрид' => 'гибрид'];

    public static function isSeriesRef(mixed $ref): bool
    {
        return is_string($ref) && preg_match(self::REF_PATTERN, $ref) === 1;
    }

    public static function isEquipmentKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^\d{1,20}_\d{1,20}_\d{1,20}$/', $key) === 1;
    }

    public function imageUrl(?string $path): ?string
    {
        return $path === null || $path === '' ? null : route('catalog-images.show', ['path' => $path], false);
    }

    /**
     * Marks that have at least one Series, popular first.
     *
     * @return EloquentCollection<int, AutoMark>
     */
    public function marks(): EloquentCollection
    {
        return AutoMark::query()->ordered()->whereHas('models.series')->get();
    }

    public function mark(string $url): ?AutoMark
    {
        return AutoMark::query()->where('url', $url)->first();
    }

    /**
     * Models of a mark that have Series, current models first, each with its newest series image.
     *
     * @return EloquentCollection<int, AutoModel>
     */
    public function models(AutoMark $mark): EloquentCollection
    {
        return $mark->models()
            ->whereHas('series')
            ->addSelect(['image' => AutoSeries::query()
                ->select('auto_series.image')
                ->join('auto_generations', 'auto_generations.id', '=', 'auto_series.generation_id')
                ->whereColumn('auto_series.model_id', 'auto_models.id')
                ->whereNotNull('auto_series.image')
                ->orderByRaw('coalesce(auto_generations.year_to, 9999) desc')
                ->orderByDesc('auto_generations.year_from')
                ->orderBy('auto_series.id')
                ->limit(1)])
            ->orderByRaw('year_to is not null')
            ->orderByDesc('year_from')
            ->orderBy('name')
            ->get();
    }

    public function model(AutoMark $mark, string $url): ?AutoModel
    {
        return $mark->models()->where('url', $url)->first();
    }

    /**
     * Generations newest first, each with its referencable Series.
     *
     * @return EloquentCollection<int, AutoGeneration>
     */
    public function generations(AutoModel $model): EloquentCollection
    {
        return $model->generations()
            ->whereNotNull('external_id')
            ->whereHas('series')
            ->with(['series' => fn ($query) => $query->whereNotNull('external_id')->orderBy('id')])
            ->orderByRaw('coalesce(year_to, 9999) desc')
            ->orderByDesc('year_from')
            ->orderByDesc('id')
            ->get();
    }

    public function series(mixed $ref): ?AutoSeries
    {
        return $this->seriesByRefs([$ref])->first();
    }

    /**
     * @param  array<int, mixed>  $refs
     * @return Collection<string, AutoSeries>
     */
    public function seriesByRefs(array $refs): Collection
    {
        $pairs = [];

        foreach ($refs as $ref) {
            if (is_string($ref) && preg_match(self::REF_PATTERN, $ref, $match) === 1) {
                $pairs[$ref] = [(int) $match[1], (int) $match[2]];
            }
        }

        if ($pairs === []) {
            return collect();
        }

        $rows = AutoSeries::query()
            ->with('generation.model.mark')
            ->whereIn('external_id', array_values(array_unique(array_column($pairs, 1))))
            ->whereHas('generation', fn (Builder $query) => $query->whereIn('external_id', array_values(array_unique(array_column($pairs, 0)))))
            ->get();
        $byRef = [];

        foreach ($rows as $series) {
            $ref = $series->ref();

            if ($ref !== null && isset($pairs[$ref]) && ! isset($byRef[$ref])) {
                $byRef[$ref] = $series;
            }
        }

        return collect($byRef);
    }

    /**
     * @return array{mark: string, model: string, generation: string, years: string|null, series: string, title: string}
     */
    public function title(AutoSeries $series): array
    {
        $generation = $series->generation;
        $model = $generation->model;

        return [
            'mark' => $model->mark->name,
            'model' => $model->name,
            'generation' => $generation->name,
            'years' => $generation->years(),
            'series' => $series->name,
            'title' => "{$model->mark->name} {$model->name}",
        ];
    }

    /**
     * Modifications in auto.ru order (engine volume, power), each with its trims: named trims
     * when the catalog has them, otherwise the modification row itself («Базовая»).
     *
     * @return list<array{modification: AutoModification, facts: ModificationFacts, equipments: list<AutoEquipment>}>
     */
    public function trims(AutoSeries $series): array
    {
        $modifications = $series->modifications()
            ->with(['equipments' => fn ($query) => $query->orderBy('id')])
            ->orderBy('engine_volume')
            ->orderBy('engine_power')
            ->orderBy('id')
            ->get();
        $facts = $this->facts($modifications);
        $trims = [];

        foreach ($modifications as $modification) {
            $named = $modification->equipments->filter(fn (AutoEquipment $equipment): bool => $equipment->isNamed());
            $equipments = $named->isNotEmpty() ? $named : $modification->equipments->reject(fn (AutoEquipment $equipment): bool => $equipment->isNamed());

            if ($equipments->isEmpty()) {
                continue;
            }

            $trims[] = [
                'modification' => $modification,
                'facts' => $facts[$modification->id],
                'equipments' => array_values($equipments->all()),
            ];
        }

        return $trims;
    }

    /**
     * Equipment rows by Series id and external key: `"{series id}|{key}" => equipment`.
     *
     * @param  array<int, list<string>>  $keysBySeries
     * @return Collection<string, AutoEquipment>
     */
    public function equipments(array $keysBySeries): Collection
    {
        $keysBySeries = array_filter(array_map(
            fn (array $keys): array => array_values(array_unique(array_filter($keys, self::isEquipmentKey(...)))),
            $keysBySeries,
        ));

        if ($keysBySeries === []) {
            return collect();
        }

        return AutoEquipment::query()
            ->with('modification')
            ->whereIn('series_id', array_keys($keysBySeries))
            ->whereIn('external_key', array_values(array_unique(array_merge(...array_values($keysBySeries)))))
            ->get()
            ->filter(fn (AutoEquipment $equipment): bool => in_array($equipment->external_key, $keysBySeries[$equipment->series_id] ?? [], true))
            ->keyBy(fn (AutoEquipment $equipment): string => "{$equipment->series_id}|{$equipment->external_key}");
    }

    public function equipment(AutoSeries $series, mixed $key): ?AutoEquipment
    {
        return is_string($key) ? $this->equipments([$series->id => [$key]])->first() : null;
    }

    /**
     * @param  iterable<AutoModification>  $modifications
     * @return array<int, ModificationFacts>
     */
    public function facts(iterable $modifications): array
    {
        $modifications = collect($modifications);
        $incomplete = $modifications
            ->filter(fn (AutoModification $modification): bool => $modification->engine === null)
            ->map(fn (AutoModification $modification): int => $modification->id)
            ->values()
            ->all();
        $values = [];

        if ($incomplete !== []) {
            $rows = AutoCharacteristicValue::query()
                ->join('auto_characteristics', 'auto_characteristics.id', '=', 'auto_characteristic_values.characteristic_id')
                ->whereIn('auto_characteristic_values.modification_id', $incomplete)
                ->whereIn('auto_characteristics.name', array_values(self::FACT_CHARACTERISTICS))
                ->get(['auto_characteristic_values.modification_id', 'auto_characteristics.name', 'auto_characteristic_values.value']);

            foreach ($rows as $row) {
                $values[(int) $row->getAttribute('modification_id')][(string) $row->getAttribute('name')] = (string) $row->getAttribute('value');
            }
        }

        $facts = [];

        foreach ($modifications as $modification) {
            $facts[$modification->id] = $modification->engine === null
                ? $this->legacyFacts($modification, $values[$modification->id] ?? [])
                : new ModificationFacts(
                    name: $modification->name,
                    volume: $modification->engine_volume,
                    power: $modification->engine_power,
                    engine: $modification->engine,
                    transmission: $modification->transmission,
                    drive: in_array($modification->drive, self::DRIVES, true) ? $modification->drive : null,
                    consumption: $modification->consumption_100_km ?? $modification->consumption_city,
                    acceleration: $modification->acceleration_0_100,
                );
        }

        return $facts;
    }

    /**
     * Characteristic sections of each Modification in catalog order.
     *
     * @param  array<int, int>  $modificationIds
     * @return array<int, list<Section>>
     */
    public function characteristics(array $modificationIds): array
    {
        if ($modificationIds === []) {
            return [];
        }

        $values = AutoCharacteristicValue::query()
            ->with('characteristic.parent')
            ->whereIn('modification_id', $modificationIds)
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->get()
            ->sortBy(fn (AutoCharacteristicValue $value): array => [
                $value->characteristic->parent->sort ?? 0,
                $value->characteristic->parent_id ?? 0,
                $value->characteristic->sort,
                $value->characteristic_id,
            ]);
        $grouped = [];

        foreach ($values as $value) {
            $group = $value->characteristic->parent->name ?? 'Прочее';
            $grouped[$value->modification_id][$group][] = [
                'label' => $value->characteristic->name,
                'value' => trim($value->value.' '.($value->unit ?? '')),
            ];
        }

        return array_map(self::sections(...), $grouped);
    }

    /**
     * Option sections of each Equipment in catalog order.
     *
     * @param  array<int, int>  $equipmentIds
     * @return array<int, list<OptionSection>>
     */
    public function options(array $equipmentIds): array
    {
        if ($equipmentIds === []) {
            return [];
        }

        $values = AutoOptionValue::query()
            ->with('option.parent')
            ->whereIn('equipment_id', $equipmentIds)
            ->get()
            ->sortBy(fn (AutoOptionValue $value): array => [
                $value->option->parent->sort ?? 0,
                $value->option->parent_id ?? 0,
                $value->option->sort,
                $value->option_id,
            ]);
        $grouped = [];

        foreach ($values as $value) {
            $grouped[$value->equipment_id][$value->option->parent->name ?? 'Прочее'][] = [
                'name' => $value->option->name,
                'availability' => $value->is_base ? 'standard' : 'optional',
            ];
        }

        return array_map(self::sections(...), $grouped);
    }

    /**
     * @param  array<int, int>  $equipmentIds
     * @return array<int, int>
     */
    public function optionCounts(array $equipmentIds): array
    {
        if ($equipmentIds === []) {
            return [];
        }

        return AutoOptionValue::query()
            ->whereIn('equipment_id', $equipmentIds)
            ->selectRaw('equipment_id, count(*) as aggregate')
            ->groupBy('equipment_id')
            ->pluck('aggregate', 'equipment_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @template TItem
     *
     * @param  array<string, list<TItem>>  $groups
     * @return list<array{group: string, items: list<TItem>}>
     */
    private static function sections(array $groups): array
    {
        return array_map(
            fn (string $group, array $items): array => ['group' => $group, 'items' => $items],
            array_keys($groups),
            array_values($groups),
        );
    }

    /**
     * @param  array<string, string>  $values  characteristic name => value
     */
    private function legacyFacts(AutoModification $modification, array $values): ModificationFacts
    {
        $value = fn (string $fact): ?string => $values[self::FACT_CHARACTERISTICS[$fact]] ?? null;
        $volume = $value('volume');
        $power = $value('power');
        $consumption = $value('consumption');
        $drive = $value('drive') ?? (in_array($modification->transmission, self::DRIVES, true) ? $modification->transmission : null);

        return new ModificationFacts(
            name: $modification->name,
            volume: is_numeric($volume) && (int) $volume > 0 ? number_format(round((int) $volume / 1000, 1), 1, '.', '') : null,
            power: $power !== null && preg_match('/^(\d+)\s*л\.с\./u', $power, $match) === 1 ? (int) $match[1] : null,
            engine: self::ENGINES[$value('engine') ?? ''] ?? null,
            transmission: $value('transmission'),
            drive: $drive,
            consumption: $consumption !== null && preg_match('/^[\d.]+\/[\d.]+\/([\d.]+)$/', $consumption, $match) === 1 ? $match[1] : $modification->consumption_100_km,
            acceleration: $value('acceleration') ?? $modification->acceleration_0_100,
        );
    }
}
