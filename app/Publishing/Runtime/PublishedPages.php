<?php

namespace App\Publishing\Runtime;

use App\Enums\PublishedRuntimeAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Models\PublishedPage;
use App\Models\PublishedRuntimeAsset;
use App\Models\PublishedVersion;
use App\Models\Site;
use App\Models\SiteAnalyticsSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Reads the stored artifacts of a Site's active Published Version (ADR-006 §2–§3). Never touches
 * Draft rows. Cache keys always contain the version, so activating a new version simply resolves
 * new keys; nothing is flushed.
 *
 * @phpstan-type Artifact array{page_public_id: string, title: string, path: string, html: string, payload: string, seo: array<string, mixed>}
 */
final class PublishedPages
{
    public function activeVersion(Site $site): ?PublishedVersion
    {
        if ($site->active_published_version_id === null) {
            return null;
        }

        return PublishedVersion::query()
            ->select(['id', 'public_id', 'site_id', 'version_number', 'status', 'ready_at'])
            ->whereKey($site->active_published_version_id)
            ->where('site_id', $site->id)
            ->where('status', PublishedVersionStatus::Ready->value)
            ->first();
    }

    /**
     * @return Artifact|null
     */
    public function find(Site $site, PublishedVersion $version, string $path): ?array
    {
        $pageId = $this->index($site, $version)[$path] ?? null;

        if ($pageId === null) {
            return null;
        }

        return Cache::remember(
            "published:{$site->public_id}:{$version->public_id}:{$pageId}",
            (int) config('publishing.cache_ttl'),
            function () use ($version, $pageId): ?array {
                $page = PublishedPage::query()
                    ->where('published_version_id', $version->id)
                    ->where('page_public_id', $pageId)
                    ->first();

                return $page === null ? null : [
                    'page_public_id' => $page->page_public_id,
                    'title' => $page->title,
                    'path' => $page->path(),
                    'html' => $page->rendered_html,
                    // Raw stored JSON keeps empty objects as objects for exact hydration.
                    'payload' => (string) $page->getRawOriginal('hydration_json'),
                    'seo' => $page->seo_json ?? [],
                ];
            },
        );
    }

    /**
     * Public path ('' for home, otherwise the slug) => page public ID, in Page order.
     *
     * @return array<string, string>
     */
    public function index(Site $site, PublishedVersion $version): array
    {
        return Cache::remember(
            "published:{$site->public_id}:{$version->public_id}:pages",
            (int) config('publishing.cache_ttl'),
            fn (): array => PublishedPage::query()
                ->where('published_version_id', $version->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['page_public_id', 'slug', 'is_home'])
                ->mapWithKeys(fn (PublishedPage $page): array => [($page->is_home ? '' : $page->slug) => $page->page_public_id])
                ->all(),
        );
    }

    /**
     * Yandex Metrica config frozen into the version's manifest; null when the version has none.
     * Re-validated here because it is printed into an inline script.
     *
     * @return array{counter_id: string, clickmap: bool, track_links: bool, accurate_track_bounce: bool, webvisor: bool}|null
     */
    public function metrica(Site $site, PublishedVersion $version): ?array
    {
        return Cache::remember(
            "published:{$site->public_id}:{$version->public_id}:metrica",
            (int) config('publishing.cache_ttl'),
            function () use ($version): ?array {
                $manifest = PublishedVersion::query()->whereKey($version->id)->first(['id', 'public_manifest_json'])?->public_manifest_json;
                $config = $manifest['analytics']['yandex_metrica'] ?? null;

                if (! is_array($config) || ! is_string($config['counter_id'] ?? null) || preg_match(SiteAnalyticsSettings::COUNTER_PATTERN, $config['counter_id']) !== 1) {
                    return null;
                }

                return [
                    'counter_id' => $config['counter_id'],
                    'clickmap' => ($config['clickmap'] ?? true) === true,
                    'track_links' => ($config['track_links'] ?? true) === true,
                    'accurate_track_bounce' => ($config['accurate_track_bounce'] ?? true) === true,
                    'webvisor' => ($config['webvisor'] ?? false) === true,
                ];
            },
        );
    }

    /**
     * Version-scoped URL of the compiled Native CSS (ADR-009), or null when the version has none.
     */
    public function nativeStylesheet(Site $site, PublishedVersion $version): ?string
    {
        $hash = Cache::remember(
            "published:{$site->public_id}:{$version->public_id}:native-css",
            (int) config('publishing.cache_ttl'),
            fn (): string => (string) PublishedRuntimeAsset::query()
                ->where('published_version_id', $version->id)
                ->where('kind', PublishedRuntimeAssetKind::NativeCss->value)
                ->value('content_hash'),
        );

        return $hash === '' ? null : "/_landflow/runtime/{$version->public_id}/{$hash}.css";
    }

    /**
     * Public paths of the indexable Pages of the version, in Page order.
     *
     * @return list<string>
     */
    public function indexablePaths(Site $site, PublishedVersion $version): array
    {
        return Cache::remember(
            "published:{$site->public_id}:{$version->public_id}:indexable",
            (int) config('publishing.cache_ttl'),
            fn (): array => array_values(PublishedPage::query()
                ->where('published_version_id', $version->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['slug', 'is_home', 'seo_json'])
                ->filter(fn (PublishedPage $page): bool => ($page->seo_json['indexable'] ?? true) === true)
                ->map(fn (PublishedPage $page): string => $page->path())
                ->all()),
        );
    }
}
