<?php

namespace App\Http\Controllers\Studio;

use App\Blocks\BlockStateDefaults;
use App\Exceptions\InvalidBlockStateException;
use App\Http\Controllers\Concerns\ResolvesEditableTemplates;
use App\Http\Controllers\Controller;
use App\Models\BlockDefinition;
use App\Models\Template;
use App\Models\TemplateBlock;
use App\Models\TemplatePage;
use App\Templates\ArrangeTemplateBlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Block Instances of a Template Draft, edited through the existing Designer. Only catalog Blocks
 * with a published version can be added; the latest published version is pinned.
 */
class TemplateBlockController extends Controller
{
    use ResolvesEditableTemplates;

    public function __construct(private ArrangeTemplateBlocks $arrange) {}

    public function store(Request $request, string $template, string $page, BlockStateDefaults $defaults): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templatePage = $this->templatePage($found, $page);
        $validated = $request->validate(['block' => ['required', 'string', 'max:'.BlockDefinition::SLUG_MAX]]);

        $definition = BlockDefinition::query()
            ->inCatalog()
            ->whereHas('versions')
            ->where('slug', $validated['block'])
            ->first() ?? throw ValidationException::withMessages(['block' => 'Этот блок недоступен.']);
        $version = $definition->versions()->latest('id')->firstOrFail();

        $block = DB::transaction(function () use ($templatePage, $version, $defaults): TemplateBlock {
            $block = new TemplateBlock([
                'sort_order' => $templatePage->blocks()->count(),
                'state_json' => $defaults->fromSchema($version->schema_json),
            ]);
            $block->page()->associate($templatePage);
            $block->version()->associate($version);
            $block->save();

            return $block;
        });

        return $this->backTo($found, $templatePage, $block);
    }

    public function state(Request $request, string $template, string $block): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templateBlock = $this->templateBlock($found, $block);
        $validated = $request->validate(['state' => ['present', 'array']]);

        try {
            $templateBlock->update(['state_json' => $validated['state']]);
        } catch (InvalidBlockStateException $exception) {
            throw ValidationException::withMessages($exception->errors);
        }

        return $this->backTo($found, $templateBlock->page, $templateBlock);
    }

    public function move(Request $request, string $template, string $block): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templateBlock = $this->templateBlock($found, $block);
        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $this->arrange->move($templateBlock, $validated['direction'] === 'up' ? -1 : 1);

        return $this->backTo($found, $templateBlock->page, $templateBlock);
    }

    public function duplicate(Request $request, string $template, string $block): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templateBlock = $this->templateBlock($found, $block);

        $copy = DB::transaction(function () use ($templateBlock): TemplateBlock {
            $copy = new TemplateBlock(['state_json' => $templateBlock->state_json, 'sort_order' => $templateBlock->sort_order]);
            $copy->is_hidden = $templateBlock->is_hidden;
            $copy->page()->associate($templateBlock->page);
            $copy->version()->associate($templateBlock->version);
            $copy->save();
            $this->arrange->insertAfter($copy, $templateBlock);

            return $copy;
        });

        return $this->backTo($found, $templateBlock->page, $copy);
    }

    public function visibility(Request $request, string $template, string $block): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templateBlock = $this->templateBlock($found, $block);
        $validated = $request->validate(['hidden' => ['required', 'boolean']]);

        TemplateBlock::query()->whereKey($templateBlock->id)->update(['is_hidden' => (bool) $validated['hidden']]);

        return $this->backTo($found, $templateBlock->page, $templateBlock);
    }

    public function destroy(Request $request, string $template, string $block): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templateBlock = $this->templateBlock($found, $block);
        $page = $templateBlock->page;
        $templateBlock->delete();

        return $this->backTo($found, $page);
    }

    private function backTo(Template $template, TemplatePage $page, ?TemplateBlock $block = null): RedirectResponse
    {
        return to_route('studio.templates.designer', array_filter([
            'template' => $template->public_id,
            'page' => $page->public_id,
            'block' => $block?->public_id,
        ]));
    }
}
