<?php

namespace App\Catalog;

use App\Enums\MediaAngle;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesGalleryImage;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use Illuminate\Http\Request;

/**
 * Browser-safe view models of the auto.ru catalog for the platform browser and the customer
 * add-vehicle wizard. Only URLs, names, Series refs and Equipment keys leave this class.
 *
 * @phpstan-type MarkItem array{url: string, name: string, name_ru: string|null, logo: string|null, logo_dark: string|null, popular: bool}
 * @phpstan-type SeriesItem array{ref: string, name: string, image: string|null, colors_count?: int, gallery_count?: int}
 */
final class CatalogPresenter
{
    public function __construct(private AutoCatalog $catalog) {}

    /**
     * @return list<MarkItem>
     */
    public function marks(): array
    {
        return array_values($this->catalog->marks()->map(fn (AutoMark $mark): array => $this->mark($mark))->all());
    }

    /**
     * @return MarkItem
     */
    public function mark(AutoMark $mark): array
    {
        return [
            'url' => $mark->url,
            'name' => $mark->name,
            'name_ru' => $mark->name_ru,
            'logo' => $this->catalog->imageUrl($mark->logo_min),
            'logo_dark' => $this->catalog->imageUrl($mark->logo_dark),
            'popular' => (bool) $mark->popular,
        ];
    }

    /**
     * @return list<array{url: string, name: string, image: string|null, years: string|null}>
     */
    public function models(AutoMark $mark): array
    {
        return array_values($this->catalog->models($mark)->map(fn (AutoModel $model): array => [
            'url' => $model->url,
            'name' => $model->name,
            'image' => $this->catalog->imageUrl($model->getAttribute('image')),
            'years' => self::years($model->year_from, $model->year_to),
        ])->all());
    }

    /**
     * @return list<array{key: string, name: string, years: string|null, series: list<SeriesItem>}>
     */
    public function generations(AutoModel $model, bool $withMediaCounts = false): array
    {
        $generations = $this->catalog->generations($model);
        $counts = $withMediaCounts ? $this->mediaCounts($generations->flatMap(fn (AutoGeneration $generation) => $generation->series)->map(fn (AutoSeries $series): ?string => $this->ref($series, $generations))->filter()->values()->all()) : null;

        return array_values($generations->map(fn (AutoGeneration $generation): array => [
            'key' => (string) $generation->external_id,
            'name' => $generation->name,
            'years' => $generation->years(),
            'series' => array_values($generation->series->map(function (AutoSeries $series) use ($generation, $counts): array {
                $ref = "{$generation->external_id}:{$series->external_id}";

                return [
                    'ref' => $ref,
                    'name' => $series->name,
                    'image' => $this->catalog->imageUrl($series->image),
                    ...($counts === null ? [] : [
                        'colors_count' => $counts['colors'][$ref] ?? 0,
                        'gallery_count' => $counts['gallery'][$ref] ?? 0,
                    ]),
                ];
            })->all()),
        ])->all());
    }

    /**
     * @return array{ref: string, name: string, image: string|null, title: string, mark: array{url: string, name: string, logo: string|null}, model: array{url: string, name: string}, generation: array{key: string, name: string, years: string|null}}
     */
    public function series(AutoSeries $series): array
    {
        $generation = $series->generation;
        $model = $generation->model;

        return [
            'ref' => (string) $series->ref(),
            'name' => $series->name,
            'image' => $this->catalog->imageUrl($series->image),
            'title' => "{$model->mark->name} {$model->name}",
            'mark' => ['url' => $model->mark->url, 'name' => $model->mark->name, 'logo' => $this->catalog->imageUrl($model->mark->logo_min)],
            'model' => ['url' => $model->url, 'name' => $model->name],
            'generation' => ['key' => (string) $generation->external_id, 'name' => $generation->name, 'years' => $generation->years()],
        ];
    }

    /**
     * Platform colours with their angle photos and the Series gallery.
     *
     * @return array{colors: list<array{public_id: string, name: string, swatch_hex: string|null, status: bool, sort_order: int, images: list<array{public_id: string, angle: string, url: string, width: int, height: int}>}>, gallery: list<array{public_id: string, url: string, width: int, height: int}>}
     */
    public function library(string $seriesRef, bool $activeOnly = true): array
    {
        $sets = SeriesMediaSet::query()
            ->where('catalog_series_ref', $seriesRef)
            ->when($activeOnly, fn ($query) => $query->active())
            ->ordered()
            ->with('images')
            ->get();

        return [
            'colors' => array_values($sets
                ->filter(fn (SeriesMediaSet $set): bool => ! $activeOnly || $set->images->isNotEmpty())
                ->map(fn (SeriesMediaSet $set): array => [
                    'public_id' => $set->public_id,
                    'name' => $set->name,
                    'swatch_hex' => $set->swatch_hex,
                    'status' => $set->status,
                    'sort_order' => $set->sort_order,
                    'images' => array_values($set->images
                        ->sortBy(fn (SeriesMediaImage $image): int => $image->angle->position())
                        ->map(fn (SeriesMediaImage $image): array => [
                            'public_id' => $image->public_id,
                            'angle' => $image->angle->value,
                            'url' => $image->url(),
                            'width' => $image->width,
                            'height' => $image->height,
                        ])->all()),
                ])->all()),
            'gallery' => array_values(SeriesGalleryImage::query()
                ->where('catalog_series_ref', $seriesRef)
                ->ordered()
                ->get()
                ->map(fn (SeriesGalleryImage $image): array => [
                    'public_id' => $image->public_id,
                    'url' => $image->url(),
                    'width' => $image->width,
                    'height' => $image->height,
                ])->all()),
        ];
    }

    /**
     * Modifications with their trims, as on the auto.ru series page.
     *
     * @return list<array{key: string, label: string, summary: string, specs: list<array{label: string, value: string}>, equipments: list<array{key: string, name: string, base: bool, options_count: int}>}>
     */
    public function trims(AutoSeries $series): array
    {
        $trims = $this->catalog->trims($series);
        $counts = $this->catalog->optionCounts(array_merge(...array_map(
            fn (array $trim): array => array_map(fn (AutoEquipment $equipment): int => $equipment->id, $trim['equipments']),
            $trims,
        ) ?: [[]]));

        return array_map(fn (array $trim): array => [
            'key' => (string) $trim['modification']->external_id,
            'label' => $trim['facts']->label(),
            'summary' => $trim['facts']->summary(),
            'specs' => $trim['facts']->specs(),
            'equipments' => array_map(fn (AutoEquipment $equipment): array => [
                'key' => $equipment->external_key,
                'name' => $equipment->displayName(),
                'base' => ! $equipment->isNamed(),
                'options_count' => $counts[$equipment->id] ?? 0,
            ], $trim['equipments']),
        ], $trims);
    }

    /**
     * Characteristic sections of one Modification of the Series.
     *
     * @return list<array{group: string, items: list<array{label: string, value: string}>}>
     */
    public function characteristics(AutoSeries $series, mixed $modificationKey): array
    {
        $modification = is_string($modificationKey) && ctype_digit($modificationKey)
            ? $series->modifications()->where('external_id', (int) $modificationKey)->first()
            : null;

        return $modification === null ? [] : ($this->catalog->characteristics([$modification->id])[$modification->id] ?? []);
    }

    /**
     * Option sections of one Equipment of the Series.
     *
     * @return list<array{group: string, items: list<array{name: string, availability: string}>}>
     */
    public function options(AutoSeries $series, mixed $equipmentKey): array
    {
        $equipment = $this->catalog->equipment($series, $equipmentKey);

        return $equipment === null ? [] : ($this->catalog->options([$equipment->id])[$equipment->id] ?? []);
    }

    /**
     * Lazily evaluated props of the add-vehicle wizard; the page asks for one step at a time
     * through partial reloads with `mark`, `model` and `series` query parameters.
     *
     * @return array<string, \Closure>
     */
    public function wizard(Request $request): array
    {
        $mark = fn (): ?AutoMark => $this->catalog->mark($request->string('mark')->toString());
        $model = function () use ($mark, $request): ?AutoModel {
            $found = $mark();

            return $found === null ? null : $this->catalog->model($found, $request->string('model')->toString());
        };
        $series = fn (): ?AutoSeries => $this->catalog->series($request->string('series')->toString());

        return [
            'marks' => fn (): array => $this->marks(),
            'models' => fn (): ?array => ($found = $mark()) === null ? null : ['mark' => $this->mark($found), 'items' => $this->models($found)],
            'generations' => fn (): ?array => ($found = $model()) === null ? null : [
                'model' => ['url' => $found->url, 'name' => $found->name],
                'items' => $this->generations($found, true),
            ],
            'picked' => function () use ($series): ?array {
                $found = $series();

                return $found === null ? null : [
                    'series' => $this->series($found),
                    'library' => $this->library((string) $found->ref()),
                    'trims' => $this->trims($found),
                ];
            },
            'options' => function () use ($series, $request): array {
                $found = $series();

                return $found === null ? [] : $this->options($found, $request->query('equipment'));
            },
            'characteristics' => function () use ($series, $request): array {
                $found = $series();

                return $found === null ? [] : $this->characteristics($found, $request->query('modification'));
            },
            'angles' => fn (): array => MediaAngle::options(),
        ];
    }

    public static function years(?int $from, ?int $to): ?string
    {
        return match (true) {
            $from !== null && $to !== null => $from === $to ? (string) $from : "{$from}–{$to}",
            $from !== null => "с {$from}",
            default => null,
        };
    }

    /**
     * @param  array<int, string>  $refs
     * @return array{colors: array<string, int>, gallery: array<string, int>}
     */
    private function mediaCounts(array $refs): array
    {
        $count = fn (string $model): array => $model::query()
            ->whereIn('catalog_series_ref', $refs)
            ->selectRaw('catalog_series_ref, count(*) as aggregate')
            ->groupBy('catalog_series_ref')
            ->pluck('aggregate', 'catalog_series_ref')
            ->map(fn ($value): int => (int) $value)
            ->all();

        return ['colors' => $count(SeriesMediaSet::class), 'gallery' => $count(SeriesGalleryImage::class)];
    }

    /**
     * @param  iterable<AutoGeneration>  $generations
     */
    private function ref(AutoSeries $series, iterable $generations): ?string
    {
        foreach ($generations as $generation) {
            if ($generation->id === $series->generation_id) {
                return "{$generation->external_id}:{$series->external_id}";
            }
        }

        return null;
    }
}
