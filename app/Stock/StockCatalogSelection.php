<?php

namespace App\Stock;

use App\Catalog\AutoCatalog;
use App\Catalog\CatalogPresenter;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoModel;

/**
 * Cascading catalog pickers for the Stock wizard (ADR-012 / X-046).
 * Reuses CatalogPresenter / AutoCatalog; only string refs and Stock colour ids leave this class.
 */
final class StockCatalogSelection
{
    public function __construct(
        private AutoCatalog $catalog,
        private CatalogPresenter $presenter,
    ) {}

    /**
     * @return array{popular: list<array<string, mixed>>, all: list<array<string, mixed>>}
     */
    public function marks(): array
    {
        $all = $this->presenter->marks();
        $popular = array_values(array_filter($all, fn (array $mark): bool => $mark['popular']));

        return [
            'popular' => $popular,
            'all' => $all,
        ];
    }

    /**
     * @return array{mark: array<string, mixed>, items: list<array<string, mixed>>}|null
     */
    public function models(string $markUrl): ?array
    {
        $mark = $this->catalog->mark($markUrl);

        if ($mark === null) {
            return null;
        }

        return [
            'mark' => $this->presenter->mark($mark),
            'items' => $this->presenter->models($mark),
        ];
    }

    /**
     * Manufacturing years available for the model (newest first).
     *
     * @return array{mark: string, model: string, items: list<int>}|null
     */
    public function years(string $markUrl, string $modelUrl): ?array
    {
        $model = $this->resolveModel($markUrl, $modelUrl);

        if ($model === null) {
            return null;
        }

        $years = [];

        foreach ($this->catalog->generations($model) as $generation) {
            foreach ($this->yearRange($generation) as $year) {
                $years[$year] = true;
            }
        }

        $items = array_map('intval', array_keys($years));
        rsort($items);

        return [
            'mark' => $markUrl,
            'model' => $modelUrl,
            'items' => $items,
        ];
    }

    /**
     * Bodies / Series covering the selected year.
     *
     * @return array{mark: string, model: string, year: int, items: list<array<string, mixed>>}|null
     */
    public function bodies(string $markUrl, string $modelUrl, int $year): ?array
    {
        $model = $this->resolveModel($markUrl, $modelUrl);

        if ($model === null) {
            return null;
        }

        $items = [];

        foreach ($this->catalog->generations($model) as $generation) {
            if (! in_array($year, $this->yearRange($generation), true)) {
                continue;
            }

            foreach ($generation->series as $series) {
                $ref = (string) $series->ref();

                $items[] = [
                    'ref' => $ref,
                    'name' => $series->name,
                    'image' => $this->catalog->imageUrl($series->image),
                    'generation' => [
                        'key' => (string) $generation->external_id,
                        'name' => $generation->name,
                        'years' => $generation->years(),
                        'year_from' => $generation->year_from,
                        'year_to' => $generation->year_to,
                    ],
                ];
            }
        }

        return [
            'mark' => $markUrl,
            'model' => $modelUrl,
            'year' => $year,
            'items' => $items,
        ];
    }

    /**
     * Series detail with cascading tech filters and trims.
     *
     * @return array{
     *   series: array<string, mixed>,
     *   filters: array{engines: list<string>, drives: list<string>, gearboxes: list<string>},
     *   trims: list<array<string, mixed>>,
     *   colors: list<array{id: string, name: string, hex: string, gradient: string|null}>
     * }|null
     */
    public function series(string $ref): ?array
    {
        $series = $this->catalog->series($ref);

        if ($series === null) {
            return null;
        }

        $trims = $this->presenter->trims($series);

        return [
            'series' => $this->presenter->series($series),
            'filters' => [
                'engines' => $this->uniqueSpec($trims, 'Двигатель'),
                'drives' => $this->uniqueSpec($trims, 'Привод'),
                'gearboxes' => $this->uniqueSpec($trims, 'Коробка передач'),
            ],
            'trims' => $trims,
            'colors' => StockColors::all(),
        ];
    }

    /**
     * Equipment options for one modification of the Series.
     *
     * @return array{series: string, modification: string, items: list<array<string, mixed>>}|null
     */
    public function equipment(string $seriesRef, string $modificationKey): ?array
    {
        $series = $this->catalog->series($seriesRef);

        if ($series === null) {
            return null;
        }

        foreach ($this->presenter->trims($series) as $trim) {
            if ($trim['key'] !== $modificationKey) {
                continue;
            }

            return [
                'series' => $seriesRef,
                'modification' => $modificationKey,
                'items' => $trim['equipments'],
            ];
        }

        return null;
    }

    /**
     * @return list<array{id: string, name: string, hex: string, gradient: string|null}>
     */
    public function colors(): array
    {
        return StockColors::all();
    }

    private function resolveModel(string $markUrl, string $modelUrl): ?AutoModel
    {
        $mark = $this->catalog->mark($markUrl);

        return $mark === null ? null : $this->catalog->model($mark, $modelUrl);
    }

    /**
     * @return list<int>
     */
    private function yearRange(AutoGeneration $generation): array
    {
        $from = $generation->year_from;

        if ($from === null) {
            return [];
        }

        $to = $generation->year_to ?? $from;
        $start = min($from, $to);
        $end = max($from, $to);
        $years = [];

        for ($year = $start; $year <= $end; $year++) {
            $years[] = $year;
        }

        return $years;
    }

    /**
     * @param  list<array{specs: list<array{label: string, value: string}>}>  $trims
     * @return list<string>
     */
    private function uniqueSpec(array $trims, string $label): array
    {
        $values = [];

        foreach ($trims as $trim) {
            foreach ($trim['specs'] as $spec) {
                if ($spec['label'] === $label) {
                    $values[$spec['value']] = true;
                }
            }
        }

        return array_keys($values);
    }
}
