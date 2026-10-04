<?php

namespace App\Http\Controllers\Platform;

use App\Catalog\CatalogLevel;
use App\Exceptions\InvalidCatalogDataException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\CatalogModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Create/update catalog hierarchy rows. The parent comes from a public_id and is verified on
 * the server; status toggling replaces hard deletes.
 */
class CatalogEntryController extends Controller
{
    private const DECIMAL_FIELDS = ['engine_power', 'consumption_100_km', 'acceleration_0_100'];

    private const ATTRIBUTES = [
        'name' => 'Название',
        'name_ru' => 'Русское название',
        'url' => 'Сегмент адреса',
        'status' => 'Активность',
        'sort_order' => 'Сортировка',
        'year_from' => 'Начало выпуска',
        'year_to' => 'Конец выпуска',
        'class' => 'Класс',
        'country' => 'Страна',
        'engine_volume' => 'Объём двигателя',
        'engine_power' => 'Мощность',
        'consumption_100_km' => 'Расход',
        'acceleration_0_100' => 'Разгон',
        'parent' => 'Родительская запись',
    ];

    public function store(Request $request, string $level): RedirectResponse
    {
        $level = CatalogLevel::from($level);
        $parent = null;

        if ($level->parent() !== null) {
            $parent = $level->parent()->find($request->string('parent')->toString())
                ?? throw ValidationException::withMessages(['parent' => 'Выберите родительскую запись.']);
        }

        $class = $level->modelClass();
        $entry = new $class;
        $data = $this->validated($request, $level, $parent, null);

        if ($parent !== null) {
            $entry->setAttribute((string) $level->parentKey(), $parent->getKey());
        }

        $this->save($entry, $data);

        return back();
    }

    public function update(Request $request, string $level, string $entry): RedirectResponse
    {
        $level = CatalogLevel::from($level);
        $model = $level->find($entry);
        abort_if($model === null, 404);

        $parentKey = $level->parentKey();
        $parent = $parentKey === null ? null : $level->parent()?->modelClass()::query()->whereKey($model->getAttribute($parentKey))->first();

        $this->save($model, $this->validated($request, $level, $parent, $model));

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, CatalogLevel $level, ?CatalogModel $parent, ?CatalogModel $entry): array
    {
        $input = $request->all();

        foreach (self::DECIMAL_FIELDS as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = str_replace([',', ' '], ['.', ''], trim($input[$field]));
            }
        }

        foreach ($input as $key => $value) {
            if ($value === '') {
                $input[$key] = null;
            }
        }

        return Validator::make($input, $level->rules($parent, $entry), [], self::ATTRIBUTES)->validate();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function save(CatalogModel $entry, array $data): void
    {
        $group = array_key_exists('group', $data) ? $data['group'] : false;
        unset($data['group']);
        $entry->fill($data);

        if ($entry instanceof AutoModel && $group !== false) {
            $entry->parent_id = $group === null ? null : AutoModel::query()
                ->where('public_id', $group)
                ->where('mark_id', $entry->mark_id)
                ->value('id')
                ?? throw ValidationException::withMessages(['group' => 'Группа должна быть моделью той же марки.']);
        }

        try {
            $entry->save();
        } catch (InvalidCatalogDataException) {
            throw ValidationException::withMessages(['name' => 'Запись нарушает правила каталога: проверьте группу модели и годы выпуска.']);
        }
    }
}
