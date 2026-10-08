<?php

namespace Tests\Feature\Templates;

use App\Enums\Entitlement;
use App\Enums\SiteType;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\Catalog\AutoGeneration;
use App\Models\Catalog\AutoMark;
use App\Models\Catalog\AutoModel;
use App\Models\Catalog\AutoSeries;
use App\Models\Form;
use App\Models\Plan;
use App\Models\Popup;
use App\Models\Site;
use App\Models\SiteVehicle;
use App\Models\Submission;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use App\Templates\ProvisionLeadPopup;
use Database\Seeders\OfficialBlockSeeder;
use Database\Seeders\OfficialChatTemplateSeeder;
use Database\Seeders\OfficialQuizTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RefreshCatalogDatabase;
use Tests\TestCase;

/**
 * P9-017: the official chat Template installs only into chat Sites; the visitor's answers and
 * chosen Site vehicle reach the lead through the existing Submission pipeline. Nothing is stored
 * besides the Submission (no transcript, D-094).
 */
class ChatSelectionSiteFlowTest extends TestCase
{
    use RefreshCatalogDatabase, RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    private Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OfficialBlockSeeder::class, OfficialQuizTemplateSeeder::class, OfficialChatTemplateSeeder::class]);
        $this->template = Template::query()->where('slug', OfficialChatTemplateSeeder::SLUG)->sole();
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, 5);
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
    }

    public function test_chat_template_is_published_and_installs_only_into_chat_sites(): void
    {
        $this->assertSame([SiteType::ChatSelection], $this->template->siteTypes());
        $this->assertSame('1.0.0', $this->template->versions()->sole()->version);

        foreach ([SiteType::Landing, SiteType::Quiz] as $type) {
            $this->createSite($type)->assertSessionHasErrors(['template' => 'Шаблон не подходит для выбранного формата сайта.']);
        }

        $this->createSite(SiteType::ChatSelection)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $chat = BlockInstance::query()->sole();
        $popup = Popup::query()->sole();
        $this->assertSame(SiteType::ChatSelection, $site->site_type);
        $this->assertSame('chat-selection', $chat->version->definition->slug);
        $this->assertSame(ProvisionLeadPopup::CHAT_FORM_NAME, Form::query()->sole()->name);
        $this->assertSame(['type' => 'open_popup', 'popup' => $popup->public_id], $chat->state_json['button']['action']);
    }

    public function test_answers_and_chosen_vehicle_are_resolved_by_the_backend(): void
    {
        $this->createSite(SiteType::ChatSelection)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $chat = BlockInstance::query()->sole();
        $vehicle = $this->vehicle($site);
        $answers = array_map(fn (array $step): string => $step['options'][1]['id'], $chat->state_json['steps']);

        $this->submit($site, ['block' => $chat->public_id, 'answers' => $answers, 'vehicle' => $vehicle->public_id])->assertCreated();

        $trusted = Submission::query()->sole()->context['trusted'] ?? [];
        $this->assertSame([
            ['question' => 'Для чего нужен автомобиль?', 'answer' => 'Для семьи'],
            ['question' => 'Как планируете покупку?', 'answer' => 'Кредит'],
        ], $trusted['answers']['items'] ?? null);
        $this->assertSame($vehicle->public_id, $trusted['vehicle']['public_id'] ?? null);
        $this->assertSame('Чат-подбор', $trusted['block']['name'] ?? null);
    }

    public function test_foreign_vehicle_or_wrong_answers_are_rejected(): void
    {
        $this->createSite(SiteType::ChatSelection)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $chat = BlockInstance::query()->sole();
        $answers = array_map(fn (array $step): string => $step['options'][0]['id'], $chat->state_json['steps']);
        $foreign = $this->vehicle(Site::factory()->create());

        $this->submit($site, ['block' => $chat->public_id, 'answers' => $answers, 'vehicle' => $foreign->public_id])->assertStatus(422);
        $this->submit($site, ['block' => $chat->public_id, 'answers' => array_reverse($answers)])->assertStatus(422);
        $this->assertSame(0, Submission::query()->count());
    }

    private function vehicle(Site $site): SiteVehicle
    {
        $mark = AutoMark::factory()->create(['name' => 'Changan', 'url' => 'changan-'.$site->id]);
        $model = AutoModel::factory()->for($mark, 'mark')->create(['name' => 'UNI-K', 'url' => 'uni-k']);
        $generation = AutoGeneration::factory()->for($model, 'model')->create(['name' => 'I', 'url' => 'i']);
        $series = AutoSeries::factory()->for($generation, 'generation')->create(['name' => 'Кроссовер', 'url' => 'suv']);

        return SiteVehicle::factory()->for($site)->forSeries($series)->create();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function submit(Site $site, array $context): TestResponse
    {
        return $this->asOwner()->postJson(route('sites.preview.submissions.store', [$site, Form::query()->sole()->public_id]), [
            'fields' => ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33'],
            'context' => $context,
        ]);
    }

    private function createSite(SiteType $type): TestResponse
    {
        return $this->asOwner()->post(route('sites.store'), [
            'name' => 'Чат-сайт',
            'site_type' => $type->value,
            'start' => 'template',
            'template' => $this->template->public_id,
        ]);
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
