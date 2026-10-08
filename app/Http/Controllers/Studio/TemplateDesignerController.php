<?php

namespace App\Http\Controllers\Studio;

use App\Blocks\BlockCatalogAccess;
use App\Http\Controllers\Concerns\ResolvesEditableTemplates;
use App\Http\Controllers\Controller;
use App\Models\BlockDefinition;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Support\SiteDesignTokens;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The existing Designer, preview and device frame for a Template Draft (P9-007). Same props shape as
 * the Site Designer, without Site-only resources (assets, vehicles, Popups, Forms, integrations).
 */
class TemplateDesignerController extends Controller
{
    use ResolvesEditableTemplates;

    public function designer(Request $request, string $template): Response
    {
        $found = $this->editableTemplate($request, $template);
        [$pages, $page] = $this->pages($request, $found);
        $blocks = $page->blocks()->with('version.definition')->get();

        return Inertia::render('studio/templates/designer', [
            ...$this->common($found, $pages, $page, $blocks, includeHidden: true),
            'selectedBlock' => $blocks->firstWhere('public_id', $request->query('block'))?->public_id,
            'library' => $this->library(),
        ]);
    }

    public function preview(Request $request, string $template): Response
    {
        $found = $this->editableTemplate($request, $template);
        [$pages, $page] = $this->pages($request, $found);

        return Inertia::render('studio/templates/preview', [
            'template' => ['public_id' => $found->public_id, 'name' => $found->name],
            'page' => ['public_id' => $page->public_id, 'title' => $page->title],
            'pages' => $this->pageList($pages),
        ]);
    }

    public function frame(Request $request, string $template): Response
    {
        $found = $this->editableTemplate($request, $template);
        [$pages, $page] = $this->pages($request, $found);
        $blocks = $page->blocks()->where('is_hidden', false)->with('version.definition')->get();

        return Inertia::render('studio/templates/frame', $this->common($found, $pages, $page, $blocks, includeHidden: false));
    }

    /**
     * @return array{0: Collection<int, TemplatePage>, 1: TemplatePage}
     */
    private function pages(Request $request, Template $template): array
    {
        $pages = $template->pages()->get();
        $page = $request->filled('page')
            ? $pages->firstWhere('public_id', $request->string('page')->toString())
            : $pages->firstWhere('is_home', true);
        abort_if($page === null, 404);

        return [$pages, $page];
    }

    /**
     * @param  Collection<int, TemplatePage>  $pages
     * @param  Collection<int, TemplateBlock>  $blocks
     * @return array<string, mixed>
     */
    private function common(Template $template, Collection $pages, TemplatePage $page, Collection $blocks, bool $includeHidden): array
    {
        return [
            'template' => [
                'public_id' => $template->public_id,
                'name' => $template->name,
                'owner_scope' => $template->owner_scope->value,
            ],
            'design' => SiteDesignTokens::resolve(null),
            'page' => ['public_id' => $page->public_id, 'title' => $page->title],
            'pages' => $this->pageList($pages),
            'blocks' => $blocks
                ->filter(fn (TemplateBlock $block): bool => $includeHidden || ! $block->is_hidden)
                ->map(fn (TemplateBlock $block): array => [
                    'public_id' => $block->public_id,
                    'slug' => $block->version->definition->slug,
                    'name' => $block->version->definition->name,
                    'version' => $block->version->version,
                    'is_hidden' => $block->is_hidden,
                    'schema' => $block->version->schema_json,
                    'sandbox' => $block->version->sandboxSource(),
                    'state' => (object) $block->state_json,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, TemplatePage>  $pages
     * @return list<array{public_id: string, title: string, slug: string, is_home: bool, seo: array{title: null, description: null, noindex: bool}}>
     */
    private function pageList(Collection $pages): array
    {
        return array_values($pages
            ->map(fn (TemplatePage $page): array => [
                'public_id' => $page->public_id,
                'title' => $page->title,
                'slug' => $page->slug,
                'is_home' => $page->is_home,
                'seo' => ['title' => null, 'description' => null, 'noindex' => false],
            ])
            ->all());
    }

    /**
     * Catalog Blocks with a published version; access applies when a Site uses the Template (P9-015).
     *
     * @return list<array{slug: string, name: string, author: string|null, access: array{mode: string, restricted: bool, label: string, detail: string|null}, available: bool, reason: null}>
     */
    private function library(): array
    {
        return array_values(BlockDefinition::query()
            ->inCatalog()
            ->whereHas('versions')
            ->with('developerProfile')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (BlockDefinition $definition): int => $definition->isPlatformOwned() ? 0 : 1)
            ->map(fn (BlockDefinition $definition): array => [
                'slug' => $definition->slug,
                'name' => $definition->name,
                'author' => $definition->developerProfile?->display_name,
                'access' => BlockCatalogAccess::card($definition),
                'available' => true,
                'reason' => null,
            ])
            ->all());
    }
}
