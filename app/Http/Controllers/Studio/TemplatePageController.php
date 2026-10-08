<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Concerns\ResolvesEditableTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavePageRequest;
use App\Models\Template;
use App\Models\TemplatePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pages of a Template Draft; the home Page always exists and cannot be removed.
 */
class TemplatePageController extends Controller
{
    use ResolvesEditableTemplates;

    public function store(Request $request, string $template): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $validated = $this->validatePage($request, $found, null);

        $page = new TemplatePage([
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?? $this->uniqueSlug($found, $validated['title']),
            'sort_order' => (int) $found->pages()->max('sort_order') + 1,
        ]);
        $page->template()->associate($found);
        $page->save();

        return $this->designer($found, $page);
    }

    public function update(Request $request, string $template, string $page): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templatePage = $this->templatePage($found, $page);
        $validated = $this->validatePage($request, $found, $templatePage);

        $templatePage->title = $validated['title'];

        if (! $templatePage->is_home && isset($validated['slug'])) {
            $templatePage->slug = $validated['slug'];
        }

        $templatePage->save();

        return $this->designer($found, $templatePage);
    }

    public function destroy(Request $request, string $template, string $page): RedirectResponse
    {
        $found = $this->editableTemplate($request, $template);
        $templatePage = $this->templatePage($found, $page);

        if ($templatePage->is_home) {
            throw ValidationException::withMessages(['page' => 'Главную страницу шаблона удалить нельзя.']);
        }

        DB::transaction(function () use ($templatePage): void {
            $templatePage->blocks()->delete();
            $templatePage->delete();
        });

        return to_route('studio.templates.designer', $found->public_id);
    }

    /**
     * @return array{title: string, slug?: string|null}
     */
    private function validatePage(Request $request, Template $template, ?TemplatePage $page): array
    {
        /** @var array{title: string, slug?: string|null} $validated */
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:'.SavePageRequest::SLUG_PATTERN,
                Rule::unique('template_pages', 'slug')->where('template_id', $template->id)->ignore($page?->id),
            ],
        ], [
            'slug.regex' => 'Адрес может содержать только строчные латинские буквы, цифры и дефисы.',
            'slug.unique' => 'Страница с таким адресом уже есть в шаблоне.',
        ], ['title' => 'название страницы', 'slug' => 'адрес страницы']);

        return $validated;
    }

    private function uniqueSlug(Template $template, string $title): string
    {
        $base = Str::limit(Str::slug($title), 90, '') ?: 'page';
        $slug = $base;

        for ($suffix = 2; TemplatePage::query()->where('template_id', $template->id)->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function designer(Template $template, TemplatePage $page): RedirectResponse
    {
        return to_route('studio.templates.designer', ['template' => $template->public_id, 'page' => $page->public_id]);
    }
}
