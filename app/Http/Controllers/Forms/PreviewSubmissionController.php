<?php

namespace App\Http\Controllers\Forms;

use App\Enums\SubmissionMode;
use App\Forms\SubmissionPipeline;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Test submission from the authenticated draft preview (X-021). Requires `preview_site` in the
 * Site's own Workspace and a Form of that Site; the Submission is stored with mode `preview`,
 * so it is never treated as a real lead.
 */
class PreviewSubmissionController extends Controller
{
    public function store(Request $request, Site $site, string $form, DesignerScope $scope, SubmissionPipeline $pipeline): JsonResponse
    {
        $scope->site($site);
        Gate::authorize('preview', $site);
        abort_unless($site->forms()->where('public_id', $form)->exists(), 404);

        $result = $pipeline->handle($form, $request->json()->all(), $request->ip(), $request->userAgent(), SubmissionMode::Preview);

        return response()->json(
            $result->status === 422 ? ['message' => $result->message, 'errors' => (object) $result->errors] : ['message' => $result->message],
            $result->status,
        );
    }
}
