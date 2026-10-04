<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Enums\SiteStatus;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicFormEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = Form::factory()->withLeadFields()->create(['success_message' => 'Спасибо, ждите звонка.']);
    }

    public function test_guest_submits_a_valid_form_without_csrf_token(): void
    {
        $this->submit(['fields' => $this->fields()])
            ->assertCreated()
            ->assertExactJson(['message' => 'Спасибо, ждите звонка.']);

        $route = Route::getRoutes()->getByName('forms.submissions.store');
        $this->assertNotNull($route);
        $this->assertContains(ValidateCsrfToken::class, $route->excludedMiddleware());
        $this->assertNotContains('auth', $route->gatherMiddleware());
    }

    public function test_field_values_are_validated_by_the_backend(): void
    {
        $this->submit(['fields' => $this->fields(['phone' => ''])])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Проверьте правильность заполнения формы.')
            ->assertJsonStructure(['errors' => ['phone']]);

        $this->submit(['fields' => $this->fields(['consent' => false])])
            ->assertUnprocessable()
            ->assertJsonPath('errors.consent', 'Подтвердите согласие.');

        $this->submit(['fields' => $this->fields(['name' => str_repeat('а', 256)])])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name']]);

        $this->submit(['fields' => $this->fields(['name' => ['nested']])])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['name']]);

        $this->submit(['fields' => $this->fields(['admin' => 'yes'])])
            ->assertUnprocessable()
            ->assertJsonPath('errors.admin', 'Неизвестное поле формы.');
    }

    public function test_typed_fields_are_checked(): void
    {
        $email = FormField::factory()->for($this->form)->create(['key' => 'email', 'type' => FormFieldType::Email, 'required' => false]);
        FormField::factory()->for($this->form)->create(['key' => 'model', 'type' => FormFieldType::Select, 'options' => ['Седан', 'Кроссовер'], 'required' => false]);
        FormField::factory()->for($this->form)->create(['key' => 'source', 'type' => FormFieldType::Hidden, 'default_value' => 'landing', 'required' => false]);

        $this->submit(['fields' => $this->fields(['email' => 'не почта'])])->assertUnprocessable()->assertJsonStructure(['errors' => ['email']]);
        $this->submit(['fields' => $this->fields(['model' => 'Грузовик'])])->assertUnprocessable()->assertJsonStructure(['errors' => ['model']]);
        $this->submit(['fields' => $this->fields(['email' => 'client@example.ru', 'model' => 'Седан', 'source' => 'подменено'])])->assertCreated();
        $this->assertSame('email', $email->key);
    }

    public function test_authoritative_or_unknown_payload_keys_are_rejected(): void
    {
        foreach (['workspace_id', 'site_id', 'destination', 'price', 'offer_price'] as $key) {
            $this->submit(['fields' => $this->fields(), $key => 1])->assertUnprocessable();
        }

        $this->submit(['name' => 'Без полей'])->assertUnprocessable();
    }

    public function test_inactive_missing_numeric_and_archived_site_forms_are_unavailable(): void
    {
        $inactive = Form::factory()->inactive()->withLeadFields()->create();
        $archived = Form::factory()->for(Site::factory()->state(['status' => SiteStatus::Archived]))->withLeadFields()->create();

        $this->submit(['fields' => $this->fields()], $inactive)->assertNotFound()->assertExactJson(['message' => 'Форма недоступна.']);
        $this->submit(['fields' => $this->fields()], $archived)->assertNotFound();
        $this->postJson('/forms/01ARZ3NDEKTSV4RRFFQ69G5FAV/submissions', ['fields' => $this->fields()])->assertNotFound()->assertExactJson(['message' => 'Форма недоступна.']);
        $this->postJson("/forms/{$this->form->id}/submissions", ['fields' => $this->fields()])->assertNotFound();
    }

    public function test_coarse_ip_backstop_limits_bursts(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->submit(['fields' => $this->fields(['phone' => ''])])->assertUnprocessable();
        }

        $this->submit(['fields' => $this->fields()])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Слишком много попыток. Попробуйте позже.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function submit(array $payload, ?Form $form = null): TestResponse
    {
        return $this->postJson(route('forms.submissions.store', ($form ?? $this->form)->public_id), $payload);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function fields(array $overrides = []): array
    {
        return ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'consent' => true, ...$overrides];
    }
}
