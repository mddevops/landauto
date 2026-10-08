<?php

namespace App\Publishing;

use App\Enums\PublishedAssetKind;
use App\Enums\PublishedVersionStatus;
use App\Models\PublishedAssetReference;
use App\Models\PublishedPage;
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
    public function __construct(private PageRenderer $renderer) {}

    /**
     * @param  list<array{kind: PublishedAssetKind, public_id: string}>  $assetReferences
     *
     * @throws PageRenderException when rendering fails or an artifact is missing or empty
     */
    public function build(PublishedVersion $version, array $assetReferences): void
    {
        if ($version->status !== PublishedVersionStatus::Building) {
            throw new LogicException('Artifacts are built only for a building Published Version.');
        }

        $manifest = $version->public_manifest_json;
        $payloads = array_values(array_map(fn (array $page): array => self::payload($version, $page), $manifest['pages']));

        // Rendering runs outside any database transaction.
        $html = $this->renderer->render($payloads);

        foreach ($manifest['pages'] as $page) {
            if (trim($html[$page['public_id']] ?? '') === '') {
                throw new PageRenderException('A published Page artifact is missing or empty.');
            }
        }

        DB::transaction(function () use ($version, $manifest, $payloads, $html, $assetReferences): void {
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
        });
    }

    /**
     * The exact public payload one Page is rendered and hydrated from. URLs are relative to the
     * Site host and scoped to this version; nothing refers to Draft routes or numeric IDs.
     *
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function payload(PublishedVersion $version, array $page): array
    {
        $manifest = $version->public_manifest_json;
        $versionId = $version->public_id;
        $forms = [];

        foreach ($manifest['forms'] as $form) {
            $forms[$form['public_id']] = $form;
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
            ], $page['blocks']),
            'assets' => array_map(fn (string $assetId): array => [
                'public_id' => $assetId,
                'url' => "/_landflow/assets/{$versionId}/{$assetId}",
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
}
