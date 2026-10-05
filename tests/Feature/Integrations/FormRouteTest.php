<?php

namespace Tests\Feature\Integrations;

use App\Enums\DeliveryDestinationType;
use App\Enums\FormFieldType;
use App\Enums\IntegrationProviderType;
use App\Enums\IntegrationStatus;
use App\Enums\WorkspaceRole;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormRoute;
use App\Models\IntegrationProfile;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class FormRouteTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $owner;

    private Site $site;

    private Form $form;

    private SiteIntegrationBinding $binding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create();
        FormField::factory()->for($this->form)->create(['key' => 'email', 'type' => FormFieldType::Email, 'label' => 'Почта', 'required' => false, 'sort_order' => 9]);
        $profile = IntegrationProfile::factory()->for($this->workspace)->create();
        $this->binding = SiteIntegrationBinding::factory()->for($this->site)->create(['integration_profile_id' => $profile->id]);
    }

    public function test_owner_adds_independent_email_and_webhook_routes(): void
    {
        $this->as($this->owner)->post($this->url('store'), [
            'destination_type' => 'email',
            'name' => 'Почта отдела продаж',
            'recipients' => ['sales@dealer.example.com', 'boss@dealer.example.com'],
            'subject' => 'Заявка с {site.name}: {vehicle.title}',
            'reply_to_field' => 'email',
        ])->assertSessionHasNoErrors();

        $this->as($this->owner)->post($this->url('store'), [
            'destination_type' => 'webhook',
            'name' => 'CRM дилера',
            'binding' => $this->binding->public_id,
            'method' => 'POST',
            'path' => '/leads',
            'headers' => [['name' => 'X-Source', 'value' => 'landflow']],
        ])->assertSessionHasNoErrors();

        [$email, $webhook] = $this->form->routes()->get()->all();
        $this->assertSame(DeliveryDestinationType::Email, $email->destination_type);
        $this->assertSame(['sales@dealer.example.com', 'boss@dealer.example.com'], $email->email_destination);
        $this->assertSame(['subject' => 'Заявка с {site.name}: {vehicle.title}', 'reply_to_field' => 'email'], $email->settings_json);
        $this->assertNull($email->site_integration_binding_id);
        $this->assertSame([$this->binding->id, $this->binding->integration_profile_id], [$webhook->site_integration_binding_id, $webhook->integration_profile_id]);
        $this->assertSame(['method' => 'POST', 'path' => '/leads', 'headers' => [['name' => 'X-Source', 'value' => 'landflow']]], $webhook->settings_json);

        $this->as($this->owner)->get($this->url('index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sites/forms/routes')
                ->has('routes', 2)
                ->where('routes.1.binding.public_id', $this->binding->public_id)
                ->missing('routes.1.binding.encrypted_credentials')
                ->has('bindings', 1));
    }

    public function test_binding_must_belong_to_the_same_site_and_match_the_provider(): void
    {
        $otherSite = Site::factory()->for($this->workspace)->create();
        $otherBinding = SiteIntegrationBinding::factory()->for($otherSite)->create(['integration_profile_id' => $this->binding->integration_profile_id]);
        $foreignBinding = SiteIntegrationBinding::factory()->create();

        foreach ([$otherBinding, $foreignBinding] as $binding) {
            $this->as($this->owner)->post($this->url('store'), $this->webhookPayload($binding->public_id))->assertSessionHasErrors('binding');
        }

        $this->as($this->owner)->post($this->url('store'), [...$this->webhookPayload($this->binding->public_id), 'destination_type' => 'custom_api'])
            ->assertSessionHasErrors('binding');

        $this->assertSame(0, FormRoute::query()->count());

        $route = new FormRoute(['name' => 'x']);
        $route->form_id = $this->form->id;
        $route->destination_type = DeliveryDestinationType::Webhook;
        $route->site_integration_binding_id = $otherBinding->id;
        $route->integration_profile_id = $otherBinding->integration_profile_id;
        $this->assertThrows(fn () => $route->save(), LogicException::class);
    }

    public function test_disabled_binding_or_profile_cannot_be_selected(): void
    {
        $this->binding->status = IntegrationStatus::Disabled;
        $this->binding->save();

        $this->as($this->owner)->post($this->url('store'), $this->webhookPayload($this->binding->public_id))->assertSessionHasErrors('binding');
    }

    public function test_validation_rejects_unsafe_route_settings(): void
    {
        $this->as($this->owner)->post($this->url('store'), [
            'destination_type' => 'email',
            'name' => 'Почта',
            'recipients' => ["evil@example.com\r\nBcc: x@example.com", 'not-an-email'],
            'subject' => 'Заявка {fields.phone}',
            'reply_to_field' => 'phone',
        ])->assertSessionHasErrors(['recipients.0', 'recipients.1', 'subject', 'reply_to_field']);

        $this->as($this->owner)->post($this->url('store'), [
            ...$this->webhookPayload($this->binding->public_id),
            'method' => 'DELETE',
            'path' => '//evil.example.com/x',
            'headers' => [['name' => 'Host', 'value' => 'internal'], ['name' => 'Authorization', 'value' => 'Bearer x'], ['name' => 'X-Ok', 'value' => "a\r\nb"]],
        ])->assertSessionHasErrors(['method', 'path', 'headers.0.name', 'headers.1.name', 'headers.2.value']);

        $this->assertSame(0, FormRoute::query()->count());
    }

    public function test_update_keeps_destination_and_disables_route(): void
    {
        $route = FormRoute::factory()->for($this->form)->webhook($this->binding)->create();

        $this->as($this->owner)->patch($this->url('update', $route), [
            ...$this->webhookPayload('ignored'),
            'destination_type' => 'email',
            'method' => 'PATCH',
            'status' => 'disabled',
        ])->assertSessionHasNoErrors();

        $route->refresh();
        $this->assertSame(DeliveryDestinationType::Webhook, $route->destination_type);
        $this->assertSame(IntegrationStatus::Disabled, $route->status);
        $this->assertSame('PATCH', $route->settings_json['method'] ?? null);
        $this->assertSame($this->binding->id, $route->site_integration_binding_id);
    }

    public function test_only_members_with_edit_form_routes_can_manage_routes(): void
    {
        $route = FormRoute::factory()->for($this->form)->create();

        foreach ([WorkspaceRole::Designer, WorkspaceRole::ContentEditor] as $role) {
            $member = User::factory()->create();
            $this->workspace->addMember($member, $role);
            $this->as($member)->get($this->url('index'))->assertForbidden();
            $this->as($member)->post($this->url('store'), $this->webhookPayload($this->binding->public_id))->assertForbidden();
            $this->as($member)->patch($this->url('update', $route), ['name' => 'x', 'status' => 'active'])->assertForbidden();
            $this->as($member)->delete($this->url('destroy', $route))->assertForbidden();
        }

        $admin = User::factory()->create();
        $this->workspace->addMember($admin, WorkspaceRole::Admin);
        $this->as($admin)->get($this->url('index'))->assertOk();
    }

    public function test_foreign_form_and_route_are_not_found(): void
    {
        $foreignForm = Form::factory()->create();
        $foreignRoute = FormRoute::factory()->for($foreignForm)->create();

        $this->as($this->owner)->get(route('sites.forms.routes.index', [$this->site, $foreignForm]))->assertNotFound();
        $this->as($this->owner)->get(route('sites.forms.routes.index', [$foreignForm->site, $foreignForm]))->assertNotFound();
        $this->as($this->owner)->delete($this->url('destroy', $foreignRoute))->assertNotFound();
        $this->assertNotNull($foreignRoute->fresh());
    }

    public function test_route_without_history_is_deleted_and_bound_binding_is_archived(): void
    {
        $route = FormRoute::factory()->for($this->form)->webhook($this->binding)->create();

        $this->as($this->owner)->delete(route('sites.integrations.destroy', [$this->site, $this->binding->public_id]));
        $this->assertSame(IntegrationStatus::Archived, $this->binding->fresh()?->status);

        $this->as($this->owner)->delete($this->url('destroy', $route))->assertRedirect();
        $this->assertNull($route->fresh());
    }

    public function test_route_destination_types_map_to_profile_provider_types(): void
    {
        $this->assertSame(IntegrationProviderType::Webhook, DeliveryDestinationType::Webhook->providerType());
        $this->assertSame(IntegrationProviderType::CustomApi, DeliveryDestinationType::CustomApi->providerType());
        $this->assertNull(DeliveryDestinationType::Email->providerType());
    }

    /**
     * @return array<string, mixed>
     */
    private function webhookPayload(string $binding): array
    {
        return ['destination_type' => 'webhook', 'name' => 'CRM', 'binding' => $binding, 'method' => 'POST'];
    }

    private function url(string $action, ?FormRoute $route = null): string
    {
        $form = $route?->form ?? $this->form;

        return route("sites.forms.routes.{$action}", array_filter([$this->site, $form, $route?->public_id]));
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
