<?php

namespace App\Http\Controllers\PublicSite;

use App\Forms\Captcha\CaptchaVerifier;
use App\Forms\SiteSecurityPolicy;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicSite;
use App\Publishing\Runtime\PublishedPages;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a published Page: the stored publish-time HTML inside a small shell with its hydration
 * payload (ADR-006 §2). Only the active Published Version is read; no Draft, no Node.
 */
class PublishedPageController extends Controller
{
    public function __invoke(Request $request, PublishedPages $pages, CaptchaVerifier $captcha, ?string $path = null): Response
    {
        $site = ResolvePublicSite::site($request);
        $version = $pages->activeVersion($site);
        $artifact = $version === null ? null : $pages->find($site, $version, trim((string) $path, '/'));

        if ($artifact === null) {
            return ResolvePublicSite::notFound();
        }

        // CAPTCHA is a live security setting: the widget follows what the server enforces now.
        $widget = SiteSecurityPolicy::forSite($site)->captchaRequired && $captcha->isConfigured() ? $captcha->widget() : null;

        return response()->view('published.page', [
            'title' => $artifact['seo']['title'] ?? $artifact['title'],
            'html' => $artifact['html'],
            'data' => json_encode(
                ['payload' => json_decode($artifact['payload'], false, 512, JSON_THROW_ON_ERROR), 'captcha' => $widget],
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
        ]);
    }

    public function missing(): Response
    {
        return ResolvePublicSite::notFound();
    }
}
