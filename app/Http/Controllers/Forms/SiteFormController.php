<?php

namespace App\Http\Controllers\Forms;

use App\Enums\FormFieldType;
use App\Forms\FormRuntime;
use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\SaveFormRequest;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use App\Support\DesignerScope;
use App\Support\SiteDesignTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer «Формы» section: Site-owned Forms and their fields (FORMS_AND_INTEGRATIONS.md §3–§5).
 * No routing or delivery configuration lives here.
 */
class SiteFormController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function index(Site $site): Response
    {
        $this->scope->site($site);
        Gate::authorize('view', $site);

        return Inertia::render('sites/forms/index', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'forms' => $site->forms()
                ->withCount('fields')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Form $form): array => [
                    'public_id' => $form->public_id,
                    'name' => $form->name,
                    'status' => $form->status,
                    'fields_count' => (int) $form->getAttribute('fields_count'),
                ])
                ->values()
                ->all(),
            'can' => ['editForms' => Gate::allows('editForms', $site)],
        ]);
    }

    public function store(SaveFormRequest $request, Site $site): RedirectResponse
    {
        $form = new Form($request->formAttributes());
        $form->site()->associate($site)->save();

        return to_route('sites.forms.show', [$site, $form]);
    }

    public function show(Site $site, Form $form, FormRuntime $runtime): Response
    {
        $this->scope->form($site, $form);
        Gate::authorize('view', $site);

        return Inertia::render('sites/forms/show', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'design' => SiteDesignTokens::resolve($site->design_tokens),
            'form' => [
                'public_id' => $form->public_id,
                'name' => $form->name,
                'status' => $form->status,
                'submit_label' => $form->submit_label,
                'success_message' => $form->success_message,
            ],
            'fields' => array_values($form->fields->map(fn (FormField $field): array => [
                'key' => $field->key,
                'type' => $field->type->value,
                'label' => $field->label,
                'placeholder' => $field->placeholder,
                'default_value' => $field->default_value,
                'required' => $field->required,
                'max_length' => $field->validation['max_length'] ?? null,
                'options' => $field->options ?? [],
            ])->all()),
            'runtime' => $runtime->present($form),
            'choices' => [
                'fieldTypes' => array_map(fn (FormFieldType $case): array => ['value' => $case->value, 'label' => $case->label()], FormFieldType::cases()),
            ],
            'can' => ['editForms' => Gate::allows('editForms', $site), 'editFormRoutes' => Gate::allows('editFormRoutes', $site)],
        ]);
    }

    public function update(SaveFormRequest $request, Site $site, Form $form): RedirectResponse
    {
        $form->update($request->formAttributes());

        return back();
    }
}
