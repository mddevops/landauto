<?php

namespace App\Http\Controllers\Stock;

use App\Automotive\VehiclePhotos;
use App\Http\Controllers\Controller;
use App\Models\StockVehicle;
use App\Models\VehiclePhoto;
use App\Stock\StockAccess;
use App\Support\ImageUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Gallery uploads for Stock vehicles (ADR-012).
 */
class StockPhotoController extends Controller
{
    public function __construct(
        private StockAccess $stock,
        private VehiclePhotos $photos,
    ) {}

    public function store(Request $request, StockVehicle $vehicle): RedirectResponse
    {
        $workspace = $this->stock->authorize($request->user());
        abort_unless($vehicle->workspace_id === $workspace->id, 404);

        $validator = Validator::make($request->all(), [
            'uploads' => ['required', 'array', 'min:1', 'max:20'],
            'uploads.*' => ImageUpload::rules(),
        ], [
            'uploads.required' => 'Выберите хотя бы одно фото.',
            ...ImageUpload::messages('uploads.*'),
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $files = $request->file('uploads');
        $files = is_array($files) ? array_values($files) : [];

        foreach ($files as $index => $file) {
            ImageUpload::checkDecoded($validator, $file, "uploads.{$index}");
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        foreach ($files as $file) {
            $this->photos->upload($vehicle, $file);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Фото добавлены.']);

        return back();
    }

    public function destroy(Request $request, StockVehicle $vehicle, VehiclePhoto $photo): RedirectResponse
    {
        $workspace = $this->stock->authorize($request->user());
        abort_unless($vehicle->workspace_id === $workspace->id, 404);
        abort_unless($photo->stock_vehicle_id === $vehicle->id && $photo->workspace_id === $workspace->id, 404);

        $this->photos->remove($photo);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Фото удалено.']);

        return back();
    }

    public function reorder(Request $request, StockVehicle $vehicle): RedirectResponse
    {
        $workspace = $this->stock->authorize($request->user());
        abort_unless($vehicle->workspace_id === $workspace->id, 404);

        $ownedIds = $vehicle->photos()->pluck('public_id')->all();

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:100'],
            'photos.*' => ['required', 'string', 'size:26', Rule::in($ownedIds)],
        ], [
            'photos.required' => 'Укажите порядок фото.',
            'photos.*.in' => 'Одно из фото не принадлежит этому автомобилю.',
        ]);

        /** @var list<string> $order */
        $order = array_values($validated['photos']);

        if (count($order) !== count(array_unique($order)) || count($order) !== count($ownedIds)) {
            throw ValidationException::withMessages([
                'photos' => 'Порядок должен включать все фото автомобиля без повторов.',
            ]);
        }

        $this->photos->reorderStockGallery($vehicle, $order);

        return back();
    }
}
