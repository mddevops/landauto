<?php

namespace App\Publishing;

use App\Blocks\Native\NativeBlockCompiler;
use App\Blocks\Native\NativeCompileException;
use App\Enums\PublishedAssetKind;
use App\Enums\PublishedRuntimeAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Models\PublishedAssetReference;
use App\Models\PublishedPage;
use App\Models\PublishedRuntimeAsset;
use App\Models\PublishedVersion;
use App\Publishing\Rendering\PageRenderer;
use App\Publishing\Rendering\PageRenderException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Renders every Page of a building Published Version from its manifest and stores the HTML
 * artifacts and asset references (ADR-006 §2, §4). It never activates the version; if any
 * Page fails, nothing is stored and the version cannot become ready.
 */
final class PublishedArtifactBuilder
{
    public const MAX_NATIVE_CSS_BYTES = 1024 * 1024;

    public function __construct(
        private PageRenderer $renderer,
        private NativeBlockCompiler $native,
    ) {}

    /**
     * @param  list<array{kind: PublishedAssetKind, public_id: string}>  $assetReferences
     *
     * @throws PageRenderException when rendering or Native compilation fails, or an artifact is missing or empty
     */
    public function build(PublishedVersion $version, array $assetReferences): void
    {
        if ($version->status !== PublishedVersionStatus::Building) {
            throw new LogicException('Artifacts are built only for a building Published Version.');
        }

        $manifest = $version->public_manifest_json;
        $payloads = array_values(array_map(fn (array $page): array => $this->payload($version, $page), $manifest['pages']));
        $nativeCss = $this->nativeStylesheet($manifest['pages']);

        // Rendering runs outside any database transaction.
        $html = $this->renderer->render($payloads);

        foreach ($manifest['pages'] as $page) {
            if (trim($html[$page['public_id']] ?? '') === '') {
                throw new PageRenderException('A published Page artifact is missing or empty.');
            }
        }

        DB::transaction(function () use ($version, $manifest, $payloads, $html, $assetReferences, $nativeCss): void {
            foreach ($manifest['pages'] as $index => $page) {
                $rendered = $html[$page['public_id']];
                $artifact = new PublishedPage;
                $artifact->forceFill([
                    'published_version_id' => $version->id,
                    'page_public_id' => $page['public_id'],
                    'slug' => $page['slug'],
                    'is_home' => $page['is_home'],
                    'title' => $page['title'],
                    'sort_order' => $page['sort_order'],
                    'rendered_html' => $rendered,
                    'hydration_json' => $payloads[$index],
                    'seo_json' => $page['seo'],
                    'content_hash' => hash('sha256', $rendered),
                ])->save();
            }

            foreach ($assetReferences as $reference) {
                $row = new PublishedAssetReference;
                $row->forceFill([
                    'published_version_id' => $version->id,
                    'kind' => $reference['kind'],
                    'reference_public_id' => $reference['public_id'],
                ])->save();
            }

            if ($nativeCss !== '') {
                $asset = new PublishedRuntimeAsset;
                $asset->forceFill([
                    'published_version_id' => $version->id,
                    'kind' => PublishedRuntimeAssetKind::NativeCss,
                    'content' => $nativeCss,
                    'content_hash' => hash('sha256', $nativeCss),
                    'byte_size' => strlen($nativeCss),
                ])->save();
            }
        });
    }

    /**
     * The exact public payload one Page is rendered and hydrated from. URLs are relative to the
     * Site host and scoped to this version; nothing refers to Draft routes or numeric IDs. Native
     * Blocks carry only compiled output (scope, safe HTML, action keys), never their source.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private function payload(PublishedVersion $version, array $page): array
    {
        $manifest = $version->public_manifest_json;
        $versionId = $version->public_id;
        $forms = [];
        $assetUrls = [];

        foreach ($manifest['forms'] as $form) {
            $forms[$form['public_id']] = $form;
        }

        foreach ($manifest['assets'] as $assetId) {
            $assetUrls[$assetId] = "/_landflow/assets/{$versionId}/{$assetId}";
        }

        return [
            'version' => $versionId,
            'site' => ['name' => $manifest['site']['name']],
            'page' => ['public_id' => $page['public_id'], 'title' => $page['title']],
            'pages' => array_map(fn (array $sitePage): array => [
                'public_id' => $sitePage['public_id'],
                'path' => $sitePage['is_home'] ? '/' : '/'.$sitePage['slug'],
            ], $manifest['pages']),
            'design' => $manifest['design'],
            'blocks' => array_map(fn (array $block): array => [
                'public_id' => $block['public_id'],
                'slug' => $block['definition'],
                'state' => (object) $block['state'],
                ...(isset($block['sandbox']) ? ['sandbox' => $block['sandbox']] : []),
                ...(isset($block['native']) ? ['native' => $this->native($block, $assetUrls)] : []),
            ], $page['blocks']),
            'assets' => array_map(fn (string $assetId): array => [
                'public_id' => $assetId,
                'url' => $assetUrls[$assetId],
            ], $manifest['assets']),
            'vehicles' => array_map(function (array $vehicle) use ($versionId): array {
                foreach ($vehicle['media']['sets'] as $s => $set) {
                    foreach ($set['images'] as $i => $image) {
                        $vehicle['media']['sets'][$s]['images'][$i]['url'] = "/_landflow/media/{$versionId}/{$image['public_id']}";
                    }
                }

                return $vehicle;
            }, $manifest['vehicles']),
            'popups' => array_map(fn (array $popup): array => [
                ...$popup,
                'form' => is_string($popup['form']) ? ($forms[$popup['form']] ?? null) : null,
            ], $manifest['popups']),
            'form_action' => "/_landflow/forms/{$versionId}",
        ];
    }

    /**
     * @param  array<string, mixed>  $block  manifest block entry with its Native source
     * @param  array<string, string>  $assetUrls
     * @return array{scope: string, html: string, actions: list<string>}
     */
    private function native(array $block, array $assetUrls): array
    {
        [$scope, $source] = self::nativeSource($block);

        try {
            return $this->native->render($scope, $source, $block['state'], fn (string $assetId): ?string => $assetUrls[$assetId] ?? null)->toPayload();
        } catch (NativeCompileException) {
            throw new PageRenderException('A Native Block failed to compile.');
        }
    }

    /**
     * One deduplicated stylesheet for the whole version: each Native Block Version once, in
     * first-use order, already scoped to its version root.
     *
     * @param  list<array<string, mixed>>  $pages
     */
    private function nativeStylesheet(array $pages): string
    {
        $stylesheets = [];

        foreach ($pages as $page) {
            foreach ($page['blocks'] as $block) {
                if (! isset($block['native'])) {
                    continue;
                }

                [$scope, $source] = self::nativeSource($block);

                try {
                    $stylesheets[$scope] ??= $this->native->stylesheet($scope, $source);
                } catch (NativeCompileException) {
                    throw new PageRenderException('A Native Block stylesheet failed to compile.');
                }
            }
        }

        $css = implode("\n", array_filter($stylesheets, fn (string $css): bool => $css !== ''));

        if (strlen($css) > self::MAX_NATIVE_CSS_BYTES) {
            throw new PageRenderException('Compiled Native CSS exceeds the size limit.');
        }

        return $css;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{0: string, 1: array{html: string, css: string, js: string, fields: list<array<string, mixed>>}}
     */
    private static function nativeSource(array $block): array
    {
        /** @var array{html: string, css: string, js: string, fields: list<array<string, mixed>>} $source */
        $source = $block['native'];

        // Defense in depth: Publish validation already refuses non-empty Native JavaScript.
        if (trim($source['js']) !== '') {
            throw new PageRenderException('Native JavaScript is not approved for publishing.');
        }

        return [NativeBlockCompiler::scope((string) $block['definition'], (string) $block['version'], $source), $source];
    }
}
