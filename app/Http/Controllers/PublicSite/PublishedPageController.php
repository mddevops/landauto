<?php

namespace App\Http\Controllers\PublicSite;

use App\Forms\Captcha\CaptchaVerifier;
use App\Forms\SiteSecurityPolicy;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePublicSite;
use App\Publishing\Runtime\PublicSiteResolver;
use App\Publishing\Runtime\PublishedPages;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a published Page: the stored publish-time HTML inside a small shell with its hydration
 * payload (ADR-006 §2). Only the active Published Version is read; no Draft, no Node.
 */
class PublishedPageController extends Controller
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

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

        $metrica = $pages->metrica($site, $version);
        $payload = json_decode($artifact['payload'], false, 512, JSON_THROW_ON_ERROR);
        $seo = $artifact['seo'];
        $description = is_string($seo['description'] ?? null) && $seo['description'] !== '' ? $seo['description'] : null;

        return response()->view('published.page', [
            'title' => is_string($seo['title'] ?? null) ? $seo['title'] : $artifact['title'],
            'description' => $description,
            'canonical' => PublicSiteResolver::primaryUrl($site, $artifact['path']),
            'robots' => ($seo['indexable'] ?? true) === true ? 'index, follow' : 'noindex, follow',
            'siteName' => is_object($payload) && is_object($payload->site ?? null) && is_string($payload->site->name ?? null) ? $payload->site->name : null,
            'html' => $artifact['html'],
            'data' => json_encode(
                ['payload' => $payload, 'captcha' => $widget, 'analytics' => ['metrica' => $metrica['counter_id'] ?? null]],
                self::JSON_FLAGS,
            ),
            'metricaCounter' => $metrica['counter_id'] ?? null,
            'metricaOptions' => $metrica === null ? null : json_encode([
                'clickmap' => $metrica['clickmap'],
                'trackLinks' => $metrica['track_links'],
                'accurateTrackBounce' => $metrica['accurate_track_bounce'],
                'webvisor' => $metrica['webvisor'],
            ], self::JSON_FLAGS),
        ]);
    }

    public function missing(): Response
    {
        return ResolvePublicSite::notFound();
    }
}
