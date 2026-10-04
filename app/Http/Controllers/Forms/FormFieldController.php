<?php

namespace App\Http\Controllers\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\SaveFormFieldRequest;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use App\Support\DesignerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Field CRUD addressed by stable key. Deleting a field never touches stored Submissions,
 * which keep their own snapshot (FORMS_AND_INTEGRATIONS.md §32).
 */
class FormFieldController extends Controller
{
    public function __construct(private DesignerScope $scope) {}

    public function store(SaveFormFieldRequest $request, Site $site, Form $form): RedirectResponse
    {
        $field = new FormField([
            ...$request->fieldAttributes(),
            'sort_order' => ((int) $form->fields()->max('sort_order')) + 1,
        ]);
        $field->key = $request->string('key')->toString();
        $field->type = $request->fieldType();
        $field->form()->associate($form)->save();

        return back();
    }

    public function update(SaveFormFieldRequest $request, Site $site, Form $form, string $field): RedirectResponse
    {
        $request->existingField()?->update($request->fieldAttributes());

        return back();
    }

    public function destroy(Site $site, Form $form, string $field): RedirectResponse
    {
        $this->authorizeForm($site, $form);

        $form->fields()->where('key', $field)->firstOrFail()->delete();

        return back();
    }

    public function order(Request $request, Site $site, Form $form): RedirectResponse
    {
        $this->authorizeForm($site, $form);

        $keys = $request->validate([
            'keys' => ['required', 'array', 'max:'.SaveFormFieldRequest::MAX_FIELDS],
            'keys.*' => ['required', 'string', 'distinct'],
        ])['keys'];
        $fields = $form->fields()->get()->keyBy('key');

        if (count($keys) !== $fields->count() || array_diff($keys, $fields->keys()->all()) !== []) {
            throw ValidationException::withMessages(['keys' => 'Список полей устарел. Обновите страницу.']);
        }

        DB::transaction(function () use ($keys, $fields): void {
            foreach (array_values($keys) as $position => $key) {
                $fields[$key]?->update(['sort_order' => $position]);
            }
        });

        return back();
    }

    private function authorizeForm(Site $site, Form $form): void
    {
        $this->scope->form($site, $form);
        Gate::authorize('editForms', $site);
    }
}
