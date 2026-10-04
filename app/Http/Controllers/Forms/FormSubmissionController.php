<?php

namespace App\Http\Controllers\Forms;

use App\Enums\SiteStatus;
use App\Forms\SubmissionFieldValidator;
use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated Form submission endpoint addressed only by the Form `public_id`.
 * Site, Workspace and any destination are derived from the Form, never from the payload
 * (FORMS_AND_INTEGRATIONS.md §42). Reachability is "active Form of an active Site"; which
 * published hosts may call it is decided by Phase 5 publishing.
 */
class FormSubmissionController extends Controller
{
    /** Top-level payload keys a visitor may send; anything else is rejected. */
    public const PAYLOAD_KEYS = ['fields'];

    public function store(Request $request, string $form, SubmissionFieldValidator $validator): JsonResponse
    {
        $model = Form::query()->with(['fields', 'site'])->where('public_id', $form)->first();

        if ($model === null || ! $model->status || $model->site->status !== SiteStatus::Active) {
            return response()->json(['message' => 'Форма недоступна.'], 404);
        }

        $payload = $request->json()->all();
        $fields = $payload['fields'] ?? null;

        if (array_diff(array_keys($payload), self::PAYLOAD_KEYS) !== [] || ! is_array($fields)) {
            return $this->invalid([]);
        }

        $result = $validator->validate($model, $fields);

        if ($result['errors'] !== []) {
            return $this->invalid($result['errors']);
        }

        return response()->json(['message' => $model->success_message], 201);
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function invalid(array $errors): JsonResponse
    {
        return response()->json([
            'message' => 'Проверьте правильность заполнения формы.',
            'errors' => (object) $errors,
        ], 422);
    }
}
