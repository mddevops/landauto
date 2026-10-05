<?php

namespace App\Http\Controllers\Forms;

use App\Enums\DeliveryDestinationType;
use App\Enums\FormFieldType;
use App\Enums\IntegrationStatus;
use App\Http\Controllers\Controller;
use App\Integrations\Delivery\EmailSubject;
use App\Integrations\Http\SafeHeaders;
use App\Integrations\Mapping\FieldMapper;
use App\Integrations\Mapping\MappingSources;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormRoute;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Support\DesignerScope;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Передача заявок» of a Form: independent email and HTTP routes (FORMS_AND_INTEGRATIONS.md §21).
 * Requires `edit_form_routes`. HTTP routes use an active binding of this Site; the destination
 * URL, method, headers and credentials are fixed here, never by the visitor.
 */
class FormRouteController extends Controller
{
    public const MAX_ROUTES = 10;

    public const MAX_RECIPIENTS = 5;

    public const MAX_HEADERS = 10;

    public const METHODS = ['POST', 'PUT', 'PATCH'];

    public const PATH_PATTERN = '/^\/(?!\/)[A-Za-z0-9._~!$&\'()*+,;=:@%\/-]*$/';

    public function __construct(private DesignerScope $scope) {}

    public function index(Site $site, Form $form): Response
    {
        $this->scope->form($site, $form);
        Gate::authorize('editFormRoutes', $site);

        return Inertia::render('sites/forms/routes', [
            'site' => ['public_id' => $site->public_id, 'name' => $site->name],
            'form' => ['public_id' => $form->public_id, 'name' => $form->name],
            'fields' => array_values($form->fields->map(fn (FormField $field): array => [
                'key' => $field->key,
                'type' => $field->type->value,
                'label' => $field->label,
            ])->all()),
            'routes' => array_values($form->routes()
                ->with('binding.profile')
                ->where('status', '!=', IntegrationStatus::Archived->value)
                ->get()
                ->map(fn (FormRoute $route): array => self::present($route))
                ->all()),
            'bindings' => array_values($site->integrationBindings()
                ->with('profile')
                ->where('status', IntegrationStatus::Active->value)
                ->get()
                ->filter(fn (SiteIntegrationBinding $binding): bool => $binding->profile->isActive())
                ->map(fn (SiteIntegrationBinding $binding): array => [
                    'value' => $binding->public_id,
                    'label' => ($binding->name ?? $binding->profile->name).' — '.$binding->profile->provider_type->label(),
                    'provider_type' => $binding->profile->provider_type->value,
                    'overrides' => array_map('strval', array_keys($binding->overrides_json)),
                ])
                ->all()),
            'sources' => array_map(
                fn (string $key, array $meta): array => ['value' => $key, 'group' => $meta[0], 'label' => $meta[1]],
                array_keys(MappingSources::FIXED),
                array_values(MappingSources::FIXED),
            ),
            'choices' => [
                'destinations' => array_map(fn (DeliveryDestinationType $type): array => ['value' => $type->value, 'label' => $type->label()], DeliveryDestinationType::cases()),
                'methods' => array_map(fn (string $method): array => ['value' => $method, 'label' => $method], self::METHODS),
                'subjectPlaceholders' => array_map(fn (string $key): string => '{'.$key.'}', array_keys(EmailSubject::PLACEHOLDERS)),
            ],
        ]);
    }

    public function store(Request $request, Site $site, Form $form): RedirectResponse
    {
        $this->scope->form($site, $form);
        Gate::authorize('editFormRoutes', $site);

        if ($form->routes()->where('status', '!=', IntegrationStatus::Archived->value)->count() >= self::MAX_ROUTES) {
            throw ValidationException::withMessages(['destination_type' => 'У формы может быть не больше '.self::MAX_ROUTES.' маршрутов.']);
        }

        $type = DeliveryDestinationType::tryFrom((string) $request->input('destination_type'));
        $validated = $request->validate([
            'destination_type' => ['required', Rule::enum(DeliveryDestinationType::class)],
            'binding' => [Rule::requiredIf($type !== null && $type !== DeliveryDestinationType::Email), 'nullable', 'string'],
            ...$this->rules($form, $type),
        ], [], self::attributes());

        $route = new FormRoute;
        $route->form_id = $form->id;
        $route->destination_type = DeliveryDestinationType::from($validated['destination_type']);

        if ($route->destination_type !== DeliveryDestinationType::Email) {
            $binding = $this->binding($site, (string) $validated['binding'], $route->destination_type);
            $route->site_integration_binding_id = $binding->id;
            $route->integration_profile_id = $binding->integration_profile_id;
            $this->validateMapping($form, $binding, $validated['mapping'] ?? []);
        }

        $route->sort_order = (int) $form->routes()->max('sort_order') + 1;
        $this->fill($route, $validated);
        $route->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Маршрут добавлен.']);

        return back();
    }

    public function update(Request $request, Site $site, Form $form, string $route): RedirectResponse
    {
        $model = $this->find($site, $form, $route);
        Gate::authorize('editFormRoutes', $site);

        $validated = $request->validate([
            ...$this->rules($form, $model->destination_type),
            'status' => ['required', Rule::in([IntegrationStatus::Active->value, IntegrationStatus::Disabled->value])],
        ], [], self::attributes());

        if ($model->binding !== null) {
            $this->validateMapping($form, $model->binding, $validated['mapping'] ?? []);
        }

        $this->fill($model, $validated);
        $model->status = IntegrationStatus::from($validated['status']);
        $model->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Маршрут сохранён.']);

        return back();
    }

    /**
     * Routes with delivery history are archived so the history keeps its destination.
     */
    public function destroy(Site $site, Form $form, string $route): RedirectResponse
    {
        $model = $this->find($site, $form, $route);
        Gate::authorize('editFormRoutes', $site);

        if (self::hasHistory($model)) {
            $model->status = IntegrationStatus::Archived;
            $model->save();
        } else {
            $model->delete();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Маршрут удалён.']);

        return back();
    }

    public static function hasHistory(FormRoute $route): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(FormRoute $route): array
    {
        $settings = $route->settings_json ?? [];

        return [
            'public_id' => $route->public_id,
            'name' => $route->name,
            'destination_type' => $route->destination_type->value,
            'destination_label' => $route->destination_type->label(),
            'status' => $route->status->value,
            'status_label' => $route->status->label(),
            'recipients' => $route->email_destination ?? [],
            'subject' => $settings['subject'] ?? '',
            'reply_to_field' => $settings['reply_to_field'] ?? '',
            'binding' => $route->binding === null ? null : [
                'public_id' => $route->binding->public_id,
                'name' => $route->binding->name ?? $route->binding->profile->name,
                'base_url' => $route->binding->profile->base_url,
            ],
            'method' => $settings['method'] ?? 'POST',
            'path' => $settings['path'] ?? '',
            'headers' => $settings['headers'] ?? [],
            'mapping' => $route->mapping_json ?? [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     */
    private function validateMapping(Form $form, SiteIntegrationBinding $binding, array $rules): void
    {
        $fieldKeys = $form->fields->map(fn (FormField $field): string => $field->key)->all();
        $overrideKeys = array_map('strval', array_keys($binding->overrides_json));

        $errors = FieldMapper::validate($rules, $fieldKeys, $overrideKeys);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function find(Site $site, Form $form, string $publicId): FormRoute
    {
        $this->scope->form($site, $form);

        return $form->routes()
            ->where('public_id', strtolower($publicId))
            ->where('status', '!=', IntegrationStatus::Archived->value)
            ->firstOrFail();
    }

    private function binding(Site $site, string $publicId, DeliveryDestinationType $type): SiteIntegrationBinding
    {
        $binding = $site->integrationBindings()
            ->with('profile')
            ->where('public_id', strtolower($publicId))
            ->where('status', IntegrationStatus::Active->value)
            ->first();

        if ($binding === null || ! $binding->profile->isActive() || $binding->profile->provider_type !== $type->providerType()) {
            throw ValidationException::withMessages(['binding' => 'Выберите активное подключение сайта подходящего типа.']);
        }

        return $binding;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(Form $form, ?DeliveryDestinationType $type): array
    {
        $rules = ['name' => ['required', 'string', 'max:120']];

        if ($type === DeliveryDestinationType::Email) {
            $emailFields = $form->fields->filter(fn (FormField $field): bool => $field->type === FormFieldType::Email)->pluck('key')->all();

            return [
                ...$rules,
                'recipients' => ['required', 'array', 'min:1', 'max:'.self::MAX_RECIPIENTS],
                'recipients.*' => ['required', 'string', 'max:254', 'email:rfc', 'distinct'],
                'subject' => ['nullable', 'string', 'max:'.EmailSubject::MAX_LENGTH, 'regex:/^[^\x00-\x1F\x7F]*$/', function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && EmailSubject::unknownPlaceholders($value) !== []) {
                        $fail('В теме можно использовать только подстановки '.implode(', ', array_map(fn (string $key): string => '{'.$key.'}', array_keys(EmailSubject::PLACEHOLDERS))).'.');
                    }
                }],
                'reply_to_field' => ['nullable', 'string', Rule::in($emailFields)],
            ];
        }

        if ($type === null) {
            return $rules;
        }

        return [
            ...$rules,
            'mapping' => ['nullable', 'array', 'max:'.FieldMapper::MAX_RULES],
            'mapping.*' => ['array'],
            'method' => ['required', Rule::in(self::METHODS)],
            'path' => ['nullable', 'string', 'max:512', 'regex:'.self::PATH_PATTERN],
            'headers' => ['nullable', 'array', 'max:'.self::MAX_HEADERS],
            'headers.*.name' => ['required', 'string', 'distinct:ignore_case', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! SafeHeaders::isAllowedName($value)) {
                    $fail('Этот заголовок задавать нельзя.');
                }
            }],
            'headers.*.value' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! SafeHeaders::isAllowedValue($value)) {
                    $fail('Значение заголовка должно быть одной строкой до 255 символов.');
                }
            }],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fill(FormRoute $route, array $validated): void
    {
        $route->name = $validated['name'];

        if ($route->destination_type === DeliveryDestinationType::Email) {
            $route->email_destination = array_values(array_map('strval', $validated['recipients']));
            $route->settings_json = array_filter([
                'subject' => $validated['subject'] ?? null,
                'reply_to_field' => $validated['reply_to_field'] ?? null,
            ], fn (mixed $value): bool => $value !== null && $value !== '');

            return;
        }

        $mapping = array_values(array_map(fn (array $rule): array => FieldMapper::normalize($rule), $validated['mapping'] ?? []));
        $route->mapping_json = $mapping === [] ? null : $mapping;
        $route->settings_json = array_filter([
            'method' => $validated['method'],
            'path' => $validated['path'] ?? null,
            'headers' => array_values(array_map(fn (array $header): array => ['name' => (string) $header['name'], 'value' => (string) $header['value']], $validated['headers'] ?? [])),
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @return array<string, string>
     */
    private static function attributes(): array
    {
        return [
            'name' => 'Название',
            'destination_type' => 'Куда передавать',
            'binding' => 'Подключение',
            'status' => 'Статус',
            'recipients' => 'Получатели',
            'recipients.*' => 'Адрес получателя',
            'subject' => 'Тема письма',
            'reply_to_field' => 'Адрес для ответа',
            'method' => 'Метод',
            'path' => 'Путь',
            'headers' => 'Заголовки',
            'headers.*.name' => 'Имя заголовка',
            'headers.*.value' => 'Значение заголовка',
        ];
    }
}
