<?php

namespace App\Http\Controllers\Platform;

use App\Catalog\AutoCatalog;
use App\Http\Controllers\Controller;
use App\Models\SeriesMediaImage;
use App\Models\SeriesMediaSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Platform Series Media Library (ADR-010): colours of a Series, each with up to five angle photos.
 */
class SeriesMediaController extends Controller
{
    public function __construct(private AutoCatalog $catalog) {}

    public function store(Request $request, string $series): RedirectResponse
    {
        $series = $this->catalog->series($series) ?? abort(404);
        $set = new SeriesMediaSet;
        $set->catalog_series_ref = (string) $series->ref();
        $set->fill($this->validated($request, $set->catalog_series_ref, null))->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Цвет «{$set->name}» добавлен."]);

        return back();
    }

    public function update(Request $request, SeriesMediaSet $set): RedirectResponse
    {
        $set->fill($this->validated($request, $set->catalog_series_ref, $set))->save();

        return back();
    }

    /**
     * Removes the colour with its photos. Customer vehicles and Sites hold their own copies.
     */
    public function destroy(SeriesMediaSet $set): RedirectResponse
    {
        $paths = $set->images()->pluck('path')->all();

        DB::transaction(function () use ($set): void {
            $set->images()->delete();
            $set->delete();
        });
        Storage::disk(SeriesMediaImage::DISK)->delete($paths);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Цвет «{$set->name}» удалён. Машины клиентов не изменились."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $seriesRef, ?SeriesMediaSet $set): array
    {
        $request->merge(['swatch_hex' => $request->filled('swatch_hex') ? mb_strtolower($request->string('swatch_hex')->toString()) : null]);

        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('series_media_sets', 'name')->where('catalog_series_ref', $seriesRef)->ignore($set?->id),
            ],
            'swatch_hex' => ['nullable', 'regex:'.SeriesMediaSet::SWATCH_PATTERN],
            'status' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ], [
            'name.unique' => 'Цвет с таким названием уже есть у этой модели.',
            'swatch_hex.regex' => 'Укажите цвет в формате #rrggbb.',
        ], ['name' => 'Название', 'swatch_hex' => 'Цвет образца', 'sort_order' => 'Сортировка']);
    }
}
