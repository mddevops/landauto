<?php

namespace App\Publishing;

use App\Automotive\SectionCatalog;
use App\Automotive\VehiclePages;
use App\Enums\SectionPageRole;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteSection;
use App\Models\SiteVehicle;
use App\Models\SiteVehicleSlugRedirect;
use Symfony\Component\Uid\Ulid;

/**
 * Catalog section of a Publish (ADR-011 §5): every address of the section becomes its own manifest
 * page carrying the blocks of its template Page, so the public runtime serves the section from the
 * snapshot exactly like ordinary Pages. Ids of generated pages are derived from the template and
 * the address, so the same Draft keeps the same manifest hash.
 *
 * @phpstan-import-type VehiclePage from VehiclePages
 * @phpstan-import-type PageContext from SectionCatalog
 */
final class SectionManifest
{
    /** Generated pages sort after every ordinary Page. */
    public const SORT_OFFSET = 1_000_000;

    /** Length of `published_pages.slug`. */
    public const PATH_MAX = 255;

    public function __construct(private SectionCatalog $catalog) {}

    /**
     * @param  list<string>  $published  public ids of the vehicles in the manifest
     * @param  callable(Page): array{blocks: list<array<string, mixed>>, vehicles: list<string>}  $blocks
     * @return array{catalog: array<string, mixed>, pages: list<array<string, mixed>>}|null
     */
    public function build(Site $site, array $published, callable $blocks): ?array
    {
        $section = $this->catalog->section($site);

        if ($section === null || ! $section->enabled) {
            return null;
        }

        $props = $this->catalog->props($site, $section);

        if ($props === null) {
            return null;
        }

        $vehicles = array_values(array_filter($props['vehicles'], fn (array $vehicle): bool => in_array($vehicle['public_id'], $published, true)));
        $templates = $section->pages()->with('blocks.version.definition')->get()
            ->keyBy(fn (Page $page): string => $page->section_role->value);
        $pages = [];

        foreach ($this->addresses($section, $vehicles) as $address) {
            $template = $templates->get($address['context']['role']);

            $path = implode('/', [$section->prefix, ...$address['segments']]);

            if (! $template instanceof Page || strlen($path) > self::PATH_MAX) {
                continue;
            }

            $content = $blocks($template);
            $pages[] = [
                'public_id' => self::pageId($template->public_id, $path),
                'slug' => $path,
                'is_home' => false,
                'title' => $address['title'],
                'sort_order' => self::SORT_OFFSET + count($pages),
                'seo' => [
                    'title' => $address['seo_title'] ?? $template->seo_title ?? $section->title,
                    'description' => $template->seo_description,
                    'indexable' => ! $template->seo_noindex,
                ],
                'blocks' => $content['blocks'],
                'section' => [
                    'template' => $template->public_id,
                    'context' => $address['context'],
                    'vehicles' => $content['vehicles'],
                ],
            ];
        }

        return [
            'catalog' => [
                'public_id' => $section->public_id,
                'title' => $section->title,
                'prefix' => $section->prefix,
                'pages' => $props['pages'],
                'vehicles' => $vehicles,
                'redirects' => $this->redirects($site, $section, $vehicles),
            ],
            'pages' => $pages,
        ];
    }

    /**
     * Deterministic ULID-shaped id of a generated page.
     */
    public static function pageId(string $template, string $path): string
    {
        return strtolower((string) Ulid::fromBinary(substr(hash('sha256', "{$template}|{$path}", true), 0, 16)));
    }

    /**
     * Section addresses in reading order: the section, then each mark with its vehicles (D-131).
     *
     * @param  list<VehiclePage>  $vehicles
     * @return list<array{segments: array<int, string>, title: string, seo_title: string|null, context: PageContext}>
     */
    private function addresses(SiteSection $section, array $vehicles): array
    {
        $addresses = [[
            'segments' => [],
            'title' => $section->title,
            'seo_title' => null,
            'context' => ['role' => SectionPageRole::Index->value, 'mark' => null, 'vehicle' => null],
        ]];

        foreach (collect($vehicles)->groupBy(fn (array $vehicle): string => $vehicle['mark']['segment']) as $mark => $byMark) {
            $mark = (string) $mark;
            $markName = $byMark->first()['mark']['name'];
            $addresses[] = [
                'segments' => [$mark],
                'title' => $markName,
                'seo_title' => "{$markName} — {$section->title}",
                'context' => ['role' => SectionPageRole::Mark->value, 'mark' => $mark, 'vehicle' => null],
            ];

            foreach ($byMark as $vehicle) {
                $addresses[] = [
                    'segments' => [$mark, $vehicle['slug']],
                    'title' => $vehicle['title'],
                    'seo_title' => $vehicle['title'],
                    'context' => ['role' => SectionPageRole::Item->value, 'mark' => $mark, 'vehicle' => $vehicle['public_id']],
                ];
            }
        }

        return $addresses;
    }

    /**
     * Previous section prefixes and previous vehicle addresses (`kia/old-slug` → `new-slug`).
     *
     * @param  list<VehiclePage>  $vehicles
     * @return array{prefixes: list<string>, vehicles: array<string, string>}
     */
    private function redirects(Site $site, SiteSection $section, array $vehicles): array
    {
        $prefixes = $section->redirects()->pluck('prefix')
            ->map(fn (mixed $prefix): string => (string) $prefix)
            ->sort()->values()->all();
        $byId = collect($vehicles)->keyBy('public_id');
        $moved = [];

        $site->vehicles()->whereIn('public_id', $byId->keys()->all())->with('slugRedirects')->get()
            ->each(function (SiteVehicle $vehicle) use ($byId, &$moved): void {
                /** @var VehiclePage $page */
                $page = $byId->get($vehicle->public_id);

                foreach ($vehicle->slugRedirects as $redirect) {
                    /** @var SiteVehicleSlugRedirect $redirect */
                    $moved["{$page['mark']['segment']}/{$redirect->slug}"] = $page['slug'];
                }
            });

        ksort($moved);

        return ['prefixes' => array_values(array_filter($prefixes, fn (string $prefix): bool => $prefix !== '')), 'vehicles' => $moved];
    }
}
