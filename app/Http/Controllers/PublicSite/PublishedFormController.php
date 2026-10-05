<?php

namespace App\Http\Controllers\PublicSite;

use App\Forms\SubmissionPipeline;
use App\Forms\SubmissionResult;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicSite;
use App\Models\PublishedVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Anonymous lead endpoint of a published page (ADR-006 §7): the Form definition and trusted
 * context come from the addressed Published Version of this host's Site, never from the Draft.
 * Stored with mode `public`; live anti-spam, blacklists and CAPTCHA still apply.
 */
class PublishedFormController extends Controller
{
    public function __invoke(Request $request, string $version, string $form, SubmissionPipeline $pipeline): JsonResponse
    {
        $site = ResolvePublicSite::site($request);
        $published = PublishedVersion::query()->where('site_id', $site->id)->where('public_id', strtolower($version))->first();

        $result = $published === null
            ? SubmissionResult::unavailable()
            : $pipeline->handlePublished($published->setRelation('site', $site), strtolower($form), $request->json()->all(), $request->ip(), $request->userAgent());

        return response()->json(
            $result->status === 422 ? ['message' => $result->message, 'errors' => (object) $result->errors] : ['message' => $result->message],
            $result->status,
        );
    }
}
