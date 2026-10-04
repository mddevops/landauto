<?php

namespace App\Http\Controllers\Platform;

use App\Enums\MediaAngle;
use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoSeries;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform Series Media Library: visual variants (usually colors) and their angle images.
 */
class SeriesMediaController extends Controller
{
    public function show(string $series): Response
    {
        $series = $this->series($series);
        $generation = $series->generation;
        $model = $generation->model;

        return Inertia::render('platform/catalog/media', [
            'series' => [
                'public_id' => $series->public_id,
                'name' => $series->name,
                'title' => trim("{$model->mark->name} {$model->name} {$generation->name}"),
                'mark' => $model->mark->public_id,
                'model' => $model->public_id,
                'generation' => $generation->public_id,
            ],
            'sets' => SeriesMediaSet::query()
                ->where('catalog_series_public_id', $series->public_id)
                ->ordered()
                ->with('images')
                ->get()
                ->map(fn (SeriesMediaSet $set): array => [
                    'public_id' => $set->public_id,
                    'name' => $set->name,
                    'swatch_hex' => $set->swatch_hex,
                    'status' => $set->status,
                    'sort_order' => $set->sort_order,
                    'images' => $set->images
                        ->sortBy(fn (SeriesMediaImage $image): int => $image->angle->position())
                        ->map(fn (SeriesMediaImage $image): array => [
                            'public_id' => $image->public_id,
                            'angle' => $image->angle->value,
                            'url' => $image->url(),
                            'width' => $image->width,
                            'height' => $image->height,
                        ])->values()->all(),
                ])->values()->all(),
            'angles' => array_map(
                fn (MediaAngle $angle): array => ['value' => $angle->value, 'label' => $angle->label()],
                MediaAngle::cases(),
            ),
            'can' => ['manageMedia' => Gate::allows(PlatformPermission::ManageCatalogMedia->value)],
        ]);
    }

    public function store(Request $request, string $series): RedirectResponse
    {
        $series = $this->series($series);
        $set = new SeriesMediaSet;
        $set->catalog_series_public_id = $series->public_id;
        $set->fill($this->validated($request, $series, null))->save();

        return back();
    }

    public function update(Request $request, SeriesMediaSet $set): RedirectResponse
    {
        $series = $this->series($set->catalog_series_public_id);
        $set->fill($this->validated($request, $series, $set))->save();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, AutoSeries $series, ?SeriesMediaSet $set): array
    {
        $request->merge(['swatch_hex' => $request->filled('swatch_hex') ? mb_strtolower($request->string('swatch_hex')->toString()) : null]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('series_media_sets', 'name')->where('catalog_series_public_id', $series->public_id)->ignore($set?->id),
            ],
            'swatch_hex' => ['nullable', 'regex:'.SeriesMediaSet::SWATCH_PATTERN],
            'status' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ], [], ['name' => 'Название', 'swatch_hex' => 'Цвет образца', 'sort_order' => 'Сортировка']);
    }

    private function series(string $publicId): AutoSeries
    {
        return AutoSeries::query()->with('generation.model.mark')->where('public_id', $publicId)->firstOrFail();
    }
}
