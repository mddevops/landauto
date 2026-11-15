<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Stock\StockAccess;
use App\Stock\StockCatalogSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Throttled Stock wizard catalog cascade (X-046 / ADR-012). JSON only; string refs.
 */
class StockCatalogSelectionController extends Controller
{
    public function __construct(
        private StockAccess $access,
        private StockCatalogSelection $selection,
    ) {}

    public function marks(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        return response()->json($this->selection->marks());
    }

    public function models(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        $mark = trim($request->string('mark')->toString());
        $payload = $mark === '' ? null : $this->selection->models($mark);

        return $payload === null
            ? response()->json(['message' => 'Марка не найдена.'], 404)
            : response()->json($payload);
    }

    public function years(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        $mark = trim($request->string('mark')->toString());
        $model = trim($request->string('model')->toString());
        $payload = ($mark === '' || $model === '') ? null : $this->selection->years($mark, $model);

        return $payload === null
            ? response()->json(['message' => 'Модель не найдена.'], 404)
            : response()->json($payload);
    }

    public function bodies(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        $mark = trim($request->string('mark')->toString());
        $model = trim($request->string('model')->toString());
        $year = $request->integer('year');

        if ($mark === '' || $model === '' || $year < 1950 || $year > ((int) date('Y') + 1)) {
            return response()->json(['message' => 'Укажите марку, модель и год.'], 422);
        }

        $payload = $this->selection->bodies($mark, $model, $year);

        return $payload === null
            ? response()->json(['message' => 'Модель не найдена.'], 404)
            : response()->json($payload);
    }

    public function series(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        $ref = trim($request->string('series')->toString());
        $payload = $ref === '' ? null : $this->selection->series($ref);

        return $payload === null
            ? response()->json(['message' => 'Кузов не найден.'], 404)
            : response()->json($payload);
    }

    public function equipment(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        $ref = trim($request->string('series')->toString());
        $modification = trim($request->string('modification')->toString());

        if ($ref === '' || $modification === '') {
            return response()->json(['message' => 'Укажите кузов и модификацию.'], 422);
        }

        $payload = $this->selection->equipment($ref, $modification);

        return $payload === null
            ? response()->json(['message' => 'Модификация не найдена.'], 404)
            : response()->json($payload);
    }

    public function colors(Request $request): JsonResponse
    {
        $this->access->authorize($request->user());

        return response()->json(['items' => $this->selection->colors()]);
    }
}
