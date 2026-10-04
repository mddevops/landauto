<?php

namespace App\Http\Controllers\Platform;

use App\Catalog\EquipmentCharacteristics;
use App\Catalog\EquipmentOptions;
use App\Enums\Catalog\OptionAvailability;
use App\Enums\PlatformPermission;
use App\Exceptions\InvalidCatalogDataException;
use App\Http\Controllers\Controller;
use App\Models\Catalog\AutoCharacteristic;
use App\Models\Catalog\AutoEquipment;
use App\Models\Catalog\AutoOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CatalogEquipmentController extends Controller
{
    public function show(string $equipment): Response
    {
        $equipment = $this->equipment($equipment);
        $modification = $equipment->modification;
        $series = $modification->series;
        $generation = $series->generation;
        $model = $generation->model;
        $values = $equipment->characteristicValues()->pluck('value', 'characteristic_id');
        $optionValues = $equipment->optionValues()->pluck('is_base', 'option_id');

        return Inertia::render('platform/catalog/equipment', [
            'equipment' => ['public_id' => $equipment->public_id, 'name' => $equipment->name, 'status' => $equipment->status],
            'chain' => [
                'mark' => ['public_id' => $model->mark->public_id, 'name' => $model->mark->name],
                'model' => ['public_id' => $model->public_id, 'name' => $model->name],
                'generation' => ['public_id' => $generation->public_id, 'name' => $generation->name],
                'series' => ['public_id' => $series->public_id, 'name' => $series->name],
                'modification' => ['public_id' => $modification->public_id, 'name' => $modification->name],
            ],
            'characteristicGroups' => AutoCharacteristic::query()->whereNull('parent_id')->ordered()
                ->with(['children' => fn ($query) => $query->ordered()])
                ->get()
                ->map(fn (AutoCharacteristic $group): array => [
                    'public_id' => $group->public_id,
                    'name' => $group->name,
                    'items' => $group->children->map(fn (AutoCharacteristic $parameter): array => [
                        'public_id' => $parameter->public_id,
                        'name' => $parameter->name,
                        'unit' => $parameter->unit,
                        'value' => $values[$parameter->id] ?? null,
                    ])->values()->all(),
                ])->values()->all(),
            'optionGroups' => AutoOption::query()->whereNull('parent_id')->ordered()
                ->with(['children' => fn ($query) => $query->ordered()])
                ->get()
                ->map(fn (AutoOption $group): array => [
                    'public_id' => $group->public_id,
                    'name' => $group->name,
                    'items' => $group->children->map(fn (AutoOption $option): array => [
                        'public_id' => $option->public_id,
                        'name' => $option->name,
                        'availability' => OptionAvailability::fromIsBase(
                            isset($optionValues[$option->id]) ? (bool) $optionValues[$option->id] : null,
                        )->value,
                    ])->values()->all(),
                ])->values()->all(),
            'availabilityChoices' => array_map(
                fn (OptionAvailability $availability): array => ['value' => $availability->value, 'label' => $availability->label()],
                OptionAvailability::cases(),
            ),
            'can' => ['edit' => Gate::allows(PlatformPermission::EditCatalog->value)],
        ]);
    }

    public function characteristics(Request $request, string $equipment, EquipmentCharacteristics $characteristics): RedirectResponse
    {
        $equipment = $this->equipment($equipment);
        $data = $request->validate([
            'values' => ['present', 'array'],
            'values.*' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var array<string, string|null> $values */
        $values = $data['values'];
        $this->apply(fn () => $characteristics->sync($equipment, $values));

        return back();
    }

    public function options(Request $request, string $equipment, EquipmentOptions $options): RedirectResponse
    {
        $equipment = $this->equipment($equipment);
        $data = $request->validate([
            'values' => ['present', 'array'],
            'values.*' => ['required', Rule::enum(OptionAvailability::class)],
        ]);

        /** @var array<string, string> $raw */
        $raw = $data['values'];
        $this->apply(fn () => $options->sync($equipment, array_map(
            fn (string $value): OptionAvailability => OptionAvailability::from($value),
            $raw,
        )));

        return back();
    }

    private function equipment(string $publicId): AutoEquipment
    {
        return AutoEquipment::query()
            ->with('modification.series.generation.model.mark')
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function apply(callable $write): void
    {
        try {
            $write();
        } catch (InvalidCatalogDataException) {
            throw ValidationException::withMessages(['values' => 'Список содержит неизвестные параметры. Обновите страницу.']);
        }
    }
}
