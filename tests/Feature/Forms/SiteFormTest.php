<?php

namespace Tests\Feature\Forms;

use App\Enums\FormFieldType;
use App\Enums\WorkspaceRole;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class SiteFormTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private Site $site;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->owner = User::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
    }

    public function test_owner_creates_and_configures_a_form(): void
    {
        $this->as($this->owner)->post(route('sites.forms.store', $this->site), ['name' => 'Заявка на тест-драйв'])
            ->assertSessionHasNoErrors();

        $form = Form::query()->sole();
        $this->assertSame([$this->site->id, true, Form::DEFAULT_SUBMIT_LABEL], [$form->site_id, $form->status, $form->submit_label]);

        $this->as($this->owner)->patch(route('sites.forms.update', [$this->site, $form]), [
            'name' => 'Тест-драйв',
            'status' => false,
            'submit_label' => 'Записаться',
            'success_message' => 'Заявка принята.',
        ])->assertSessionHasNoErrors();

        $form->refresh();
        $this->assertSame(['Тест-драйв', false, 'Записаться', 'Заявка принята.'], [$form->name, $form->status, $form->submit_label, $form->success_message]);

        $this->as($this->owner)->get(route('sites.forms.index', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/forms/index')
                ->where('forms.0.public_id', $form->public_id)
                ->where('can.editForms', true)
                ->missing('forms.0.id'));
    }

    public function test_fields_are_added_edited_reordered_and_deleted_by_stable_key(): void
    {
        $form = Form::factory()->for($this->site)->create();
        $fields = route('sites.forms.fields.store', [$this->site, $form]);

        $this->as($this->owner)->post($fields, $this->field(['key' => 'name', 'type' => 'text', 'label' => 'Имя', 'max_length' => 80]))->assertSessionHasNoErrors();
        $this->as($this->owner)->post($fields, $this->field(['key' => 'phone', 'type' => 'phone', 'label' => 'Телефон', 'required' => true]))->assertSessionHasNoErrors();
        $this->as($this->owner)->post($fields, $this->field(['key' => 'model', 'type' => 'select', 'label' => 'Модель', 'options' => ['Седан', ' Кроссовер ']]))->assertSessionHasNoErrors();

        $model = $form->fields()->where('key', 'model')->sole();
        $this->assertSame(['Седан', 'Кроссовер'], $model->options);
        $this->assertSame(80, $form->fields()->where('key', 'name')->sole()->maxLength());

        $this->as($this->owner)->patch(route('sites.forms.fields.update', [$this->site, $form, 'phone']), array_diff_key($this->field(['label' => 'Ваш телефон', 'required' => false]), ['key' => 0, 'type' => 0]))
            ->assertSessionHasNoErrors();
        $this->assertSame(['Ваш телефон', false], [$form->fields()->where('key', 'phone')->value('label'), (bool) $form->fields()->where('key', 'phone')->value('required')]);

        $this->as($this->owner)->put(route('sites.forms.fields.order', [$this->site, $form]), ['keys' => ['model', 'phone', 'name']])->assertSessionHasNoErrors();
        $this->assertSame(['model', 'phone', 'name'], $form->fields()->pluck('key')->all());

        $this->as($this->owner)->put(route('sites.forms.fields.order', [$this->site, $form]), ['keys' => ['model', 'phone']])->assertSessionHasErrors('keys');

        $this->as($this->owner)->delete(route('sites.forms.fields.destroy', [$this->site, $form, 'model']))->assertRedirect();
        $this->assertSame(['phone', 'name'], $form->fields()->pluck('key')->all());
    }

    public function test_field_definitions_are_validated(): void
    {
        $form = Form::factory()->for($this->site)->create();
        FormField::factory()->for($form)->create(['key' => 'phone', 'type' => FormFieldType::Phone]);
        $store = fn (array $overrides) => $this->as($this->owner)->post(route('sites.forms.fields.store', [$this->site, $form]), $this->field($overrides));

        $store(['key' => 'phone', 'type' => 'phone'])->assertSessionHasErrors('key');
        $store(['key' => 'Phone'])->assertSessionHasErrors('key');
        $store(['key' => '1st'])->assertSessionHasErrors('key');
        $store(['key' => 'with-dash'])->assertSessionHasErrors('key');
        $store(['type' => 'html'])->assertSessionHasErrors('type');
        $store(['type' => 'select'])->assertSessionHasErrors('options');
        $store(['label' => str_repeat('а', 121)])->assertSessionHasErrors('label');
        $store(['type' => 'phone', 'max_length' => 10])->assertSessionHasErrors('max_length');
        $store(['type' => 'text', 'max_length' => 300])->assertSessionHasErrors('max_length');
        $this->assertSame(1, $form->fields()->count());

        $store(['key' => 'consent', 'type' => 'consent', 'label' => str_repeat('Согласие ', 50), 'required' => true])->assertSessionHasNoErrors();
        $store(['key' => 'source', 'type' => 'hidden', 'default_value' => 'landing', 'required' => true])->assertSessionHasNoErrors();

        $hidden = $form->fields()->where('key', 'source')->sole();
        $this->assertSame(['landing', false], [$hidden->default_value, $hidden->required]);

        $this->as($this->owner)->patch(route('sites.forms.fields.update', [$this->site, $form, 'phone']), $this->field(['key' => 'mobile']))
            ->assertSessionHasErrors('key');
        $this->as($this->owner)->patch(route('sites.forms.fields.update', [$this->site, $form, 'phone']), $this->field(['type' => 'text']))
            ->assertSessionHasErrors('type');
    }

    public function test_field_key_and_type_are_immutable_on_the_model(): void
    {
        $field = FormField::factory()->create(['key' => 'phone', 'type' => FormFieldType::Phone]);

        $field->key = 'mobile';
        $this->expectException(LogicException::class);
        $field->save();
    }

    public function test_foreign_cross_site_and_numeric_form_references_are_not_found(): void
    {
        $foreign = Form::factory()->create();
        $otherSiteForm = Form::factory()->for(Site::factory()->for($this->workspace))->create();
        $payload = ['name' => 'Взлом', 'status' => true, 'submit_label' => 'Ок', 'success_message' => 'Ок'];

        $this->as($this->owner)->get(route('sites.forms.show', [$this->site, $foreign]))->assertNotFound();
        $this->as($this->owner)->patch(route('sites.forms.update', [$this->site, $otherSiteForm]), $payload)->assertNotFound();
        $this->as($this->owner)->post(route('sites.forms.fields.store', [$this->site, $otherSiteForm]), $this->field())->assertNotFound();
        $this->as($this->owner)->get(route('sites.forms.index', $foreign->site))->assertNotFound();
        $this->as($this->owner)->get("/sites/{$this->site->public_id}/forms/{$otherSiteForm->id}")->assertNotFound();
        $this->as($this->owner)->delete(route('sites.forms.fields.destroy', [$this->site, Form::factory()->for($this->site)->create(), 'missing']))->assertNotFound();

        $this->assertNotSame('Взлом', $otherSiteForm->fresh()?->name);
        $this->assertSame(0, FormField::query()->count());
    }

    public function test_form_editing_follows_edit_forms_permission(): void
    {
        $form = Form::factory()->for($this->site)->create();
        $members = [];

        foreach ([WorkspaceRole::Admin, WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $members[$role->value] = User::factory()->create();
            $this->workspace->addMember($members[$role->value], $role);
        }

        $this->as($members['admin'])->post(route('sites.forms.fields.store', [$this->site, $form]), $this->field())->assertSessionHasNoErrors();

        foreach (['designer', 'content_editor'] as $role) {
            $this->as($members[$role])->get(route('sites.forms.show', [$this->site, $form]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('can.editForms', false));
            $this->as($members[$role])->post(route('sites.forms.store', $this->site), ['name' => 'Чужая'])->assertForbidden();
            $this->as($members[$role])->post(route('sites.forms.fields.store', [$this->site, $form]), $this->field(['key' => 'other']))->assertForbidden();
            $this->as($members[$role])->delete(route('sites.forms.fields.destroy', [$this->site, $form, 'name']))->assertForbidden();
        }

        $this->assertSame(1, Form::query()->count());
        $this->assertSame(['name'], $form->fields()->pluck('key')->all());
    }

    public function test_popups_attach_only_forms_of_the_same_site_and_runtime_hides_inactive_forms(): void
    {
        $active = Form::factory()->for($this->site)->withLeadFields()->create();
        $inactive = Form::factory()->for($this->site)->inactive()->create();
        $foreign = Form::factory()->for(Site::factory()->for($this->workspace))->create();
        $payload = fn (?string $form) => [
            'name' => 'Обратный звонок', 'status' => true, 'size' => 'medium', 'animation' => 'fade',
            'close_on_overlay' => true, 'close_on_escape' => true, 'show_close_button' => true, 'mobile_fullscreen' => false,
            'form' => $form,
        ];

        $this->as($this->owner)->post(route('sites.popups.store', $this->site), $payload($foreign->public_id))->assertSessionHasErrors('form');
        $this->as($this->owner)->post(route('sites.popups.store', $this->site), $payload((string) $active->id))->assertSessionHasErrors('form');
        $this->assertSame(0, Popup::query()->count());

        $this->as($this->owner)->post(route('sites.popups.store', $this->site), $payload($active->public_id))->assertSessionHasNoErrors();
        $withActive = Popup::query()->sole();
        $this->assertSame($active->id, $withActive->form_id);

        $withInactive = Popup::factory()->for($this->site)->create();
        $withInactive->form()->associate($inactive)->save();

        $home = new Page(['title' => Page::HOME_TITLE, 'slug' => Page::HOME_SLUG, 'sort_order' => 0]);
        $home->is_home = true;
        $home->site()->associate($this->site)->save();

        $this->as($this->owner)->get(route('sites.preview', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('popups.0.public_id', $withActive->public_id)
                ->where('popups.0.form.public_id', $active->public_id)
                ->where('popups.0.form.fields.1.key', 'phone')
                ->where('popups.0.form.fields.2.type', 'consent')
                ->missing('popups.0.form.id')
                ->where('popups.1.form', null));

        $this->expectException(LogicException::class);
        $withInactive->form()->associate($foreign)->save();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function field(array $overrides = []): array
    {
        return [
            'key' => 'name',
            'type' => 'text',
            'label' => 'Имя',
            'placeholder' => 'Иван',
            'required' => false,
            ...$overrides,
        ];
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
