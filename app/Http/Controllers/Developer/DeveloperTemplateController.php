<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Concerns\ResolvesEditableTemplates;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveDeveloperProfile;
use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Models\DeveloperProfile;
use App\Models\Template;
use App\Templates\TemplateAuthoring;
use App\Templates\TemplatePresenter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Студия» → «Шаблоны» of the User's own Developer Profile (`create_templates`).
 */
class DeveloperTemplateController extends Controller
{
    use ResolvesEditableTemplates;

    public function __construct(private TemplatePresenter $presenter) {}

    public function index(Request $request): Response
    {
        return Inertia::render('developer/templates/index', [
            'templates' => Template::query()
                ->ownedByDeveloper($this->profile($request))
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
        return Inertia::render('developer/templates/create', ['siteTypes' => $this->presenter->siteTypeOptions()]);
    }

    public function store(StoreTemplateRequest $request, TemplateAuthoring $authoring): RedirectResponse
    {
        try {
            $template = $authoring->createDeveloper(
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

    private function profile(Request $request): DeveloperProfile
    {
        $profile = $request->attributes->get(EnsureActiveDeveloperProfile::ATTRIBUTE);
        abort_unless($profile instanceof DeveloperProfile, 403);

        return $profile;
    }
}
