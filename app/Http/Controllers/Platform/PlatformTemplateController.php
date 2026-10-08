<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\ResolvesEditableTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Models\Template;
use App\Templates\TemplateAuthoring;
use App\Templates\TemplatePresenter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Студия» → «Шаблоны Landflow»: official platform-owned Templates (`manage_platform_content`).
 */
class PlatformTemplateController extends Controller
{
    use ResolvesEditableTemplates;

    public function __construct(private TemplatePresenter $presenter) {}

    public function index(): Response
    {
        return Inertia::render('platform/templates/index', [
            'templates' => Template::query()
                ->platformOwned()
                ->with('latestVersion')
                ->withCount('versions')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Template $template): array => $this->presenter->listItem($template))
                ->values()
                ->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('platform/templates/create', ['siteTypes' => $this->presenter->siteTypeOptions()]);
    }

    public function store(StoreTemplateRequest $request, TemplateAuthoring $authoring): RedirectResponse
    {
        try {
            $template = $authoring->createPlatform(
                $this->actor($request),
                $request->string('name')->toString(),
                $request->string('slug')->toString(),
                $request->siteTypes(),
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['slug' => 'Этот slug уже используется другим шаблоном.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Шаблон «{$template->name}» создан."]);

        return to_route('studio.templates.designer', $template->public_id);
    }
}
