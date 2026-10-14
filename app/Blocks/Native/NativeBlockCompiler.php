<?php

namespace App\Blocks\Native;

use App\Enums\BlockRuntime;
use App\Models\BlockVersion;
use Closure;

/**
 * Publish-time compiler of Native Block Versions (ADR-009). Deterministic and PHP-only: it runs
 * in Publish validation and the artifact build, never on a public request. Input is the
 * immutable authored source, the validated Instance state and version-scoped asset URLs; output
 * is a safe HTML fragment and version-scoped CSS. Native JavaScript is never compiled here.
 *
 * @phpstan-type NativeSource array{html: string, css: string, js: string, fields: list<array<string, mixed>>}
 */
final class NativeBlockCompiler
{
    public const MAX_STYLESHEET_BYTES = 256 * 1024;

    public const SCOPE_ATTRIBUTE = 'data-landflow-native';

    /** @var array<string, NativeTemplate> compiled templates by scope */
    private array $compiled = [];

    public function __construct(
        private NativeTemplateCompiler $templates,
        private NativeTemplateRenderer $renderer,
        private NativeCssCompiler $styles,
    ) {}

    /**
     * Deterministic version-level scope, e.g. `hero--1-0-0--3f9a1c2b7d`: readable slug and
     * version plus a hash of the immutable sources.
     *
     * @param  NativeSource  $source
     */
    public static function scope(string $slug, string $version, array $source): string
    {
        $hash = substr(hash('sha256', (string) json_encode(
            [$slug, $version, $source['html'], $source['css'], $source['js'], $source['fields']],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        )), 0, 10);

        return self::slug($slug).'--'.self::slug($version).'--'.$hash;
    }

    /**
     * Source-only problems of a Native version (template structure, markup policy, stylesheet).
     *
     * @return list<array{source: 'html'|'css'|'state', line: int|null, message: string}>
     */
    public function issues(BlockVersion $version): array
    {
        $source = self::source($version);

        if ($source === null) {
            return [];
        }

        $issues = [];
        $scope = self::scope($version->definition->slug, $version->version, $source);

        foreach ([fn () => $this->template($scope, $source), fn () => $this->stylesheet($scope, $source)] as $check) {
            try {
                $check();
            } catch (NativeCompileException $exception) {
                $issues[] = ['source' => $exception->source, 'line' => $exception->sourceLine, 'message' => $exception->getMessage()];
            }
        }

        return $issues;
    }

    /**
     * @param  NativeSource  $source
     */
    public function stylesheet(string $scope, array $source): string
    {
        $css = $this->styles->compile($source['css'], '['.self::SCOPE_ATTRIBUTE.'="'.$scope.'"]', 'lf-'.substr($scope, -10).'-');

        if (strlen($css) > self::MAX_STYLESHEET_BYTES) {
            throw NativeCompileException::css('Скомпилированные стили блока больше 256 КБ.');
        }

        return $css;
    }

    /**
     * @param  NativeSource  $source
     * @param  Closure(string): ?string  $assetUrl  version-scoped URL of a Site Asset public id
     */
    public function render(string $scope, array $source, mixed $state, Closure $assetUrl): NativeRender
    {
        $template = $this->template($scope, $source);
        [$props, $urls] = NativeProps::build($source['fields'], $state, $assetUrl);

        return new NativeRender($scope, $this->renderer->render($template, $props, $urls), $template->actions);
    }

    /**
     * @return NativeSource|null
     */
    public static function source(BlockVersion $version): ?array
    {
        $source = $version->authoredSource();

        return $source === null || $version->runtime !== BlockRuntime::Native ? null : [
            'html' => $source['html'],
            'css' => $source['css'],
            'js' => $source['js'],
            'fields' => $source['fields'],
        ];
    }

    /**
     * @param  NativeSource  $source
     */
    private function template(string $scope, array $source): NativeTemplate
    {
        return $this->compiled[$scope] ??= $this->templates->compile($source['html'], $source['fields']);
    }

    private static function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    }
}
