<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Enums\SiteStatus;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

class PublicFormEndpointTest extends TestCase
{
    use RefreshDatabase, SubmitsPublishedForms;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form = Form::factory()->withLeadFields()->create(['success_message' => 'Спасибо, ждите звонка.']);
    }

    public function test_guest_submits_a_published_form_without_session_or_csrf(): void
    {
        $this->submit(['fields' => $this->fields()])
            ->assertCreated()
            ->assertExactJson(['message' => 'Спасибо, ждите звонка.'])
            ->assertCookieMissing(config('session.cookie'));

        $route = Route::getRoutes()->getByName('public.forms');
        $this->assertNotNull($route);
        $this->assertNotContains('web', $route->gatherMiddleware());
        $this->assertNotContains('auth', $route->gatherMiddleware());
        $this->assertNull(Route::getRoutes()->getByName('forms.submissions.store'));
    }

    public function test_field_values_are_validated_by_the_backend(): void
    {
        $this->submit(['fields' => $this->fields(['phone' => ''])])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Проверьте правильность заполнения формы.')
            ->assertJsonStructure(['errors' => ['phone']]);

        $this->submit(['fields' => $this->fields(['phone' => '+7 999 ABC'])])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone', 'Телефон может содержать только цифры, пробелы, скобки, дефисы и «+» в начале.');

        $this->submit(['fields' => $this->fields(['phone' => '+7 999'])])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone', 'Укажите телефон полностью, например +7 999 111-22-33.');

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

    public function test_inactive_unpublished_foreign_and_archived_site_forms_are_unavailable(): void
    {
        $inactive = Form::factory()->inactive()->withLeadFields()->create();
        $this->submit(['fields' => $this->fields()], $inactive)->assertNotFound()->assertExactJson(['message' => 'Форма недоступна.']);

        $version = $this->publishFormSite($this->form);
        $site = Site::query()->findOrFail($this->form->site_id);
        $this->postJson($this->publicUrl($site, "/_landflow/forms/{$version->public_id}/01arz3ndektsv4rrffq69g5fav"), ['fields' => $this->fields()])
            ->assertNotFound()->assertExactJson(['message' => 'Форма недоступна.']);
        $this->postJson($this->publicUrl($site, "/_landflow/forms/{$version->public_id}/{$this->form->id}"), ['fields' => $this->fields()])->assertNotFound();

        $foreign = Form::factory()->withLeadFields()->create();
        $foreignVersion = $this->publishFormSite($foreign);
        $this->postJson($this->publicUrl($site, "/_landflow/forms/{$foreignVersion->public_id}/{$foreign->public_id}"), ['fields' => $this->fields()])
            ->assertNotFound();

        $site->forceFill(['status' => SiteStatus::Archived])->save();
        $this->postJson($this->publicUrl($site, "/_landflow/forms/{$version->public_id}/{$this->form->public_id}"), ['fields' => $this->fields()])->assertNotFound();
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
        return $this->submitPublished($form ?? $this->form, $payload);
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
