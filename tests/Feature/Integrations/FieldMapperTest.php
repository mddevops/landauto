<?php

namespace Tests\Feature\Integrations;

use App\Enums\WorkspaceRole;
use App\Integrations\Mapping\FieldMapper;
use App\Integrations\Mapping\MappingContext;
use App\Integrations\Mapping\MappingFailure;
use App\Models\Form;
use App\Models\FormRoute;
use App\Models\IntegrationProfile;
use App\Models\Site;
use App\Models\SiteIntegrationBinding;
use App\Models\Submission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class FieldMapperTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private Form $form;

    private SiteIntegrationBinding $binding;

    private Submission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::factory()->create(['name' => 'Дилер Москвич', 'subdomain' => 'dealer']);
        $this->form = Form::factory()->for($this->site)->withLeadFields()->create(['name' => 'Тест-драйв']);
        $profile = IntegrationProfile::factory()->create(['workspace_id' => $this->site->workspace_id]);
        $this->binding = SiteIntegrationBinding::factory()->for($this->site)->create(['integration_profile_id' => $profile->id, 'overrides_json' => ['site_id' => '101']]);
        $this->submission = Submission::factory()->for($this->form)->create([
            'payload' => [
                ['key' => 'name', 'type' => 'text', 'label' => 'Имя', 'value' => 'Иван'],
                ['key' => 'phone', 'type' => 'phone', 'label' => 'Телефон', 'value' => '+7 (999) 111-22-33'],
                ['key' => 'consent', 'type' => 'consent', 'label' => 'Согласие', 'value' => true],
            ],
            'context' => [
                'trusted' => [
                    'vehicle' => ['public_id' => '01hzzzzzzzzzzzzzzzzzzzzzzz', 'title' => 'Москвич 3', 'mark' => 'Москвич', 'model' => '3', 'generation' => 'I', 'series' => 'Кроссовер'],
                    'offer' => ['public_id' => '01hyyyyyyyyyyyyyyyyyyyyyyy', 'modification' => '1.5 CVT', 'equipment' => 'Люкс', 'price_minor' => 199_000_050, 'currency' => 'RUB', 'price_label' => '1 990 000,50 ₽'],
                ],
                'visitor' => ['utm_source' => 'yandex', 'page_url' => 'https://dealer.example/'],
            ],
        ]);
    }

    public function test_maps_form_fields_trusted_price_constant_and_site_override(): void
    {
        $payload = $this->map([
            ['target' => 'telephone', 'source' => 'field.phone'],
            ['target' => 'client.name', 'source' => 'field.name'],
            ['target' => 'client.consent', 'source' => 'field.consent'],
            ['target' => 'price', 'source' => 'offer.price'],
            ['target' => 'price_minor', 'source' => 'offer.price_minor'],
            ['target' => 'car', 'source' => 'vehicle.title'],
            ['target' => 'source', 'source' => 'constant', 'value' => 'landflow'],
            ['target' => 'dealer.site_id', 'source' => 'override.site_id'],
            ['target' => 'utm', 'source' => 'utm.source'],
        ]);

        $this->assertSame([
            'telephone' => '+7 (999) 111-22-33',
            'client' => ['name' => 'Иван', 'consent' => true],
            'price' => 1990000.5,
            'price_minor' => 199_000_050,
            'car' => 'Москвич 3',
            'source' => 'landflow',
            'dealer' => ['site_id' => '101'],
            'utm' => 'yandex',
        ], $payload);
    }

    public function test_missing_value_behaviour_is_explicit(): void
    {
        $this->assertSame(['b' => null], $this->map([
            ['target' => 'a', 'source' => 'utm.campaign', 'missing' => 'omit'],
            ['target' => 'b', 'source' => 'utm.campaign', 'missing' => 'null'],
        ]));

        try {
            $this->map([['target' => 'campaign', 'source' => 'utm.campaign', 'missing' => 'error']]);
            $this->fail('A missing required value must fail.');
        } catch (MappingFailure $failure) {
            $this->assertSame('missing_required_value', $failure->errorCode);
            $this->assertStringContainsString('campaign', $failure->getMessage());
        }
    }

    public function test_unknown_source_and_invalid_target_fail_at_runtime(): void
    {
        $this->assertThrows(fn () => $this->map([['target' => 'x', 'source' => 'App\\Models\\User::first']]), MappingFailure::class);
        $this->assertThrows(fn () => $this->map([['target' => 'x', 'source' => '$.fields[0]']]), MappingFailure::class);
        $this->assertThrows(fn () => $this->map([['target' => '../x', 'source' => 'form.name']]), MappingFailure::class);
    }

    public function test_cross_tenant_binding_is_impossible(): void
    {
        $foreign = SiteIntegrationBinding::factory()->create(['overrides_json' => ['site_id' => '999']]);

        $this->assertThrows(fn () => MappingContext::for($this->submission, $foreign), LogicException::class);
    }

    public function test_default_payload_is_deterministic(): void
    {
        $first = $this->map([]);

        $this->assertSame($first, $this->map([]));
        $this->assertSame(['name' => 'Иван', 'phone' => '+7 (999) 111-22-33', 'consent' => true], $first['fields']);
        $this->assertSame($this->submission->public_id, $first['submission_id']);
        $this->assertSame(1990000.5, $first['offer']['price']);
        $this->assertSame('yandex', $first['utm']['source']);
        $this->assertSame('Тест-драйв', $first['form']);
        $this->assertSame('Дилер Москвич', $first['site']);
        $this->assertArrayNotHasKey('id', $first);
    }

    public function test_save_time_validation_rejects_unknown_sources_and_conflicting_targets(): void
    {
        $errors = FieldMapper::validate([
            ['target' => 'a', 'source' => 'field.missing_field'],
            ['target' => 'b', 'source' => 'override.dealer_id'],
            ['target' => 'c', 'source' => 'eval'],
            ['target' => 'd', 'source' => 'constant', 'value' => ''],
            ['target' => 'e.f', 'source' => 'form.name'],
            ['target' => 'e', 'source' => 'form.name'],
            ['target' => 'bad target', 'source' => 'form.name', 'missing' => 'explode'],
        ], ['name', 'phone'], ['site_id']);

        $this->assertSame(['mapping.0.source', 'mapping.1.source', 'mapping.2.source', 'mapping.3.value', 'mapping.5.target', 'mapping.6.target', 'mapping.6.missing'], array_keys($errors));
        $this->assertSame([], FieldMapper::validate([['target' => 'telephone', 'source' => 'field.phone'], ['target' => 'site_id', 'source' => 'override.site_id']], ['phone'], ['site_id']));
    }

    public function test_route_editor_saves_a_validated_mapping(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::query()->findOrFail($this->site->workspace_id);
        $workspace->addMember($owner, WorkspaceRole::Owner);
        $url = route('sites.forms.routes.store', [$this->site, $this->form]);
        $base = ['destination_type' => 'webhook', 'name' => 'CRM', 'binding' => $this->binding->public_id, 'method' => 'POST'];
        $as = $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $workspace->public_id]);

        $as->post($url, [...$base, 'mapping' => [['target' => 'telephone', 'source' => 'field.nope', 'missing' => 'omit']]])
            ->assertSessionHasErrors('mapping.0.source');

        $as->post($url, [...$base, 'mapping' => [
            ['target' => 'telephone', 'source' => 'field.phone', 'missing' => 'error'],
            ['target' => 'price', 'source' => 'offer.price', 'missing' => 'omit', 'value' => 'ignored'],
            ['target' => 'source', 'source' => 'constant', 'value' => 'landflow', 'missing' => 'omit', 'extra' => 'dropped'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame([
            ['target' => 'telephone', 'source' => 'field.phone', 'missing' => 'error'],
            ['target' => 'price', 'source' => 'offer.price', 'missing' => 'omit'],
            ['target' => 'source', 'source' => 'constant', 'missing' => 'omit', 'value' => 'landflow'],
        ], FormRoute::query()->sole()->mapping_json);
    }

    /**
     * @param  list<array<string, string>>  $rules
     * @return array<string, mixed>
     */
    private function map(array $rules): array
    {
        /** @var list<array{target: string, source: string, value?: string|null, missing?: string}> $rules */
        return app(FieldMapper::class)->map($rules, MappingContext::for($this->submission->fresh() ?? $this->submission, $this->binding));
    }
}
