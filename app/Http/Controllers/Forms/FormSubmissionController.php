<?php

namespace App\Http\Controllers\Forms;

use App\Forms\SubmissionPipeline;
use App\Http\Controllers\Controller;
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
    public function store(Request $request, string $form, SubmissionPipeline $pipeline): JsonResponse
    {
        $result = $pipeline->handle($form, $request->json()->all(), $request->ip(), $request->userAgent());

        return response()->json(
            $result->status === 422 ? ['message' => $result->message, 'errors' => (object) $result->errors] : ['message' => $result->message],
            $result->status,
        );
    }
}
