<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PlatformPermission;
use App\Exceptions\InvalidCatalogDataException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two-level characteristic and option dictionaries. Codes are stable and fixed after creation.
 */
class CatalogDictionaryController extends Controller
{
    public function show(string $dictionary): Response
    {
        $class = $this->modelClass($dictionary);

        return Inertia::render('platform/catalog/dictionary', [
            'dictionary' => [
                'key' => $dictionary,
                'title' => $dictionary === 'characteristics' ? 'Характеристики' : 'Опции',
                'hasUnit' => $dictionary === 'characteristics',
            ],
            'groups' => $class::query()->whereNull('parent_id')->ordered()
                ->with(['children' => fn ($query) => $query->ordered()])
                ->get()
                ->map(fn (AutoCharacteristic|AutoOption $group): array => [
                    ...$this->present($group),
                    'items' => $group->children->map(fn (AutoCharacteristic|AutoOption $entry): array => $this->present($entry))->values()->all(),
                ])->values()->all(),
            'can' => ['edit' => Gate::allows(PlatformPermission::EditCatalog->value)],
        ]);
    }

    public function store(Request $request, string $dictionary): RedirectResponse
    {
        $class = $this->modelClass($dictionary);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:'.AutoCharacteristic::CODE_PATTERN, Rule::unique("catalog.{$this->table($dictionary)}", 'code')],
            'group' => ['nullable', 'string', 'size:26'],
            ...$this->editableRules($dictionary),
        ], [], ['code' => 'Код', 'name' => 'Название', 'unit' => 'Единица', 'sort_order' => 'Сортировка']);

        $entry = new $class;
        $entry->code = $data['code'];

        if (($data['group'] ?? null) !== null) {
            $entry->parent_id = $class::query()->where('public_id', $data['group'])->whereNull('parent_id')->value('id')
                ?? throw ValidationException::withMessages(['group' => 'Выберите группу верхнего уровня.']);
        }

        $this->save($entry, $data, $dictionary);

        return back();
    }

    public function update(Request $request, string $dictionary, string $entry): RedirectResponse
    {
        $class = $this->modelClass($dictionary);
        $model = $class::query()->where('public_id', $entry)->firstOrFail();
        $data = $request->validate($this->editableRules($dictionary), [], ['name' => 'Название', 'unit' => 'Единица', 'sort_order' => 'Сортировка']);

        $this->save($model, $data, $dictionary);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function editableRules(string $dictionary): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            ...($dictionary === 'characteristics' ? ['unit' => ['nullable', 'string', 'max:32']] : []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(AutoCharacteristic|AutoOption $entry, array $data, string $dictionary): void
    {
        $entry->name = (string) $data['name'];
        $entry->sort_order = (int) $data['sort_order'];

        if ($entry instanceof AutoCharacteristic) {
            $unit = trim((string) ($data['unit'] ?? ''));
            $entry->unit = $unit === '' ? null : $unit;
        }

        try {
            $entry->save();
        } catch (InvalidCatalogDataException) {
            throw ValidationException::withMessages([
                'code' => $dictionary === 'characteristics'
                    ? 'Проверьте код и единицу: у группы нет единицы, а коды полей модификации, марки и модели зарезервированы.'
                    : 'Проверьте код и группу.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AutoCharacteristic|AutoOption $entry): array
    {
        return [
            'public_id' => $entry->public_id,
            'code' => $entry->code,
            'name' => $entry->name,
            'unit' => $entry instanceof AutoCharacteristic ? $entry->unit : null,
            'sort_order' => $entry->sort_order,
        ];
    }

    /**
     * @return class-string<AutoCharacteristic>|class-string<AutoOption>
     */
    private function modelClass(string $dictionary): string
    {
        return $dictionary === 'characteristics' ? AutoCharacteristic::class : AutoOption::class;
    }

    private function table(string $dictionary): string
    {
        return $dictionary === 'characteristics' ? 'auto_characteristics' : 'auto_options';
    }
}
