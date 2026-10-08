<?php

namespace Tests\Feature\Templates;

use App\Enums\Entitlement;
use App\Enums\SiteType;
use App\Enums\SubmissionMode;
use App\Enums\WorkspaceRole;
use App\Integrations\Mapping\MappingContext;
use App\Integrations\Mapping\MappingSources;
use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\Plan;
use App\Models\Popup;
use App\Models\Site;
use App\Models\Submission;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use App\Templates\ProvisionLeadPopup;
use Database\Seeders\OfficialBlockSeeder;
use Database\Seeders\OfficialQuizTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\SubmitsPublishedForms;
use Tests\TestCase;

/**
 * P9-016: the official quiz Template installs only into quiz Sites, gets a Site-owned lead Form in
 * a Popup, and the visitor's answers are resolved by the backend into the Submission context.
 */
class QuizSiteFlowTest extends TestCase
{
    use RefreshDatabase, SubmitsPublishedForms;

    private User $owner;

    private Workspace $workspace;

    private Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([OfficialBlockSeeder::class, OfficialQuizTemplateSeeder::class]);
        $this->template = Template::query()->where('slug', OfficialQuizTemplateSeeder::SLUG)->sole();
        $plan = Plan::factory()->create();
        $plan->setEntitlement(Entitlement::MaxSites, 5);
        $plan->setEntitlement(Entitlement::MultiPageSites, true);
        $this->owner = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['plan_id' => $plan->id]);
        $this->workspace->addMember($this->owner, WorkspaceRole::Owner);
    }

    public function test_official_quiz_template_is_published_and_seeding_is_idempotent(): void
    {
        $version = $this->template->versions()->sole();
        $this->assertTrue($this->template->isPlatformOwned());
        $this->assertSame([SiteType::Quiz], $this->template->siteTypes());
        $this->assertCount(3, $version->content_json['pages'][0]['blocks'][0]['state']['steps']);

        $this->seed(OfficialQuizTemplateSeeder::class);
        $this->assertSame(1, Template::query()->where('slug', OfficialQuizTemplateSeeder::SLUG)->count());
        $this->assertSame(1, $this->template->versions()->count());

        $this->asOwner()->get(route('sites.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('templates', fn ($templates): bool => collect($templates)->contains(fn ($template): bool => $template['public_id'] === $this->template->public_id
                    && $template['site_types'] === ['quiz']
                    && $template['author'] === null
                    && $template['available'] === true)));
    }

    public function test_quiz_template_installs_only_into_quiz_sites(): void
    {
        foreach ([SiteType::Landing, SiteType::MultiPage, SiteType::ChatSelection] as $type) {
            $this->createSite($type)->assertSessionHasErrors(['template' => 'Шаблон не подходит для выбранного формата сайта.']);
        }

        $this->assertSame(0, Site::query()->count());
    }

    public function test_installing_wires_a_site_owned_lead_form_into_the_quiz_button(): void
    {
        $this->createSite(SiteType::Quiz)->assertSessionHasNoErrors();

        $site = Site::query()->sole();
        $quiz = BlockInstance::query()->sole();
        $popup = Popup::query()->sole();
        $form = Form::query()->sole();
        $this->assertSame([$site->id, $site->id, $form->id], [$popup->site_id, $form->site_id, $popup->form_id]);
        $this->assertSame(ProvisionLeadPopup::QUIZ_FORM_NAME, $form->name);
        $this->assertSame(['name', 'phone'], $form->fields()->orderBy('sort_order')->pluck('key')->all());
        $this->assertSame(['type' => 'open_popup', 'popup' => $popup->public_id], $quiz->state_json['button']['action']);
        $this->assertSame('Какой автомобиль вы ищете?', $quiz->state_json['steps'][0]['question']);
        $this->assertNull($this->template->versions()->sole()->content_json['pages'][0]['blocks'][0]['state']['button']['action'] ?? null, 'the Template keeps no Site references');

        app(ProvisionLeadPopup::class)->provision($site, ProvisionLeadPopup::QUIZ_FORM_NAME);
        $this->assertSame(1, Form::query()->count(), 'a configured quiz button is left alone');
    }

    public function test_preview_submission_stores_answers_resolved_from_the_quiz_block(): void
    {
        $this->createSite(SiteType::Quiz)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $quiz = BlockInstance::query()->sole();
        $form = Form::query()->sole();
        $answers = $this->answers($quiz, [1, 0, 2]);

        $this->previewSubmit($site, $form, ['block' => $quiz->public_id, 'answers' => $answers])->assertCreated();

        $submission = Submission::query()->sole();
        $this->assertSame(SubmissionMode::Preview, $submission->mode);
        $this->assertSame([
            ['question' => 'Какой автомобиль вы ищете?', 'answer' => 'Кроссовер'],
            ['question' => 'Какой бюджет рассматриваете?', 'answer' => 'До 2 млн ₽'],
            ['question' => 'Когда планируете покупку?', 'answer' => 'Пока присматриваюсь'],
        ], $submission->context['trusted']['answers']['items'] ?? null);

        $mapping = new MappingContext(fields: [], overrides: [], trusted: $submission->context['trusted'], visitor: [], submissionPublicId: $submission->public_id, submittedAt: '', formPublicId: $form->public_id, formName: $form->name, sitePublicId: $site->public_id, siteName: $site->name, siteUrl: '');
        $this->assertSame("Какой автомобиль вы ищете?: Кроссовер\nКакой бюджет рассматриваете?: До 2 млн ₽\nКогда планируете покупку?: Пока присматриваюсь", MappingSources::resolve('answers', $mapping));
    }

    public function test_answers_that_do_not_match_the_quiz_are_rejected(): void
    {
        $this->createSite(SiteType::Quiz)->assertSessionHasNoErrors();
        $site = Site::query()->sole();
        $quiz = BlockInstance::query()->sole();
        $form = Form::query()->sole();
        $valid = $this->answers($quiz, [0, 0, 0]);

        foreach ([
            ['block' => $quiz->public_id, 'answers' => array_slice($valid, 0, 2)],
            ['block' => $quiz->public_id, 'answers' => [$valid[0], $valid[0], $valid[2]]],
            ['block' => $quiz->public_id, 'answers' => [...array_slice($valid, 0, 2), (string) Str::ulid()]],
            ['block' => $quiz->public_id, 'answers' => 'Кроссовер'],
            ['answers' => $valid],
        ] as $context) {
            $this->previewSubmit($site, $form, $context)->assertStatus(422);
        }

        $this->assertSame(0, Submission::query()->count());
    }

    public function test_published_submission_resolves_answers_from_the_published_version(): void
    {
        $this->createSite(SiteType::Quiz)->assertSessionHasNoErrors();
        $quiz = BlockInstance::query()->sole();
        $form = Form::query()->with('fields')->sole();
        $answers = $this->answers($quiz, [2, 1, 0]);
        $version = $this->publishFormSite($form);

        $steps = $quiz->state_json['steps'];
        $steps[0]['options'][2]['label'] = 'Изменено в черновике';
        $quiz->update(['state_json' => [...$quiz->state_json, 'steps' => $steps]]);

        $site = Site::query()->sole();
        $this->onPublicHost(fn () => $this->postJson($this->publicUrl($site, "/_landflow/forms/{$version->public_id}/{$form->public_id}"), [
            'fields' => ['name' => 'Анна', 'phone' => '+7 (999) 222-33-44'],
            'context' => ['block' => $quiz->public_id, 'answers' => $answers],
        ]))->assertCreated();

        $this->assertSame('Внедорожник', Submission::query()->sole()->context['trusted']['answers']['items'][0]['answer'] ?? null, 'labels come from the published version, not the Draft');
    }

    /**
     * @param  list<int>  $choices
     * @return list<string>
     */
    private function answers(BlockInstance $quiz, array $choices): array
    {
        return array_map(fn (array $step, int $choice): string => $step['options'][$choice]['id'], $quiz->state_json['steps'], $choices);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function previewSubmit(Site $site, Form $form, array $context): TestResponse
    {
        return $this->asOwner()->postJson(route('sites.preview.submissions.store', [$site, $form->public_id]), [
            'fields' => ['name' => 'Иван', 'phone' => '+7 (999) 111-22-33'],
            'context' => $context,
        ]);
    }

    private function createSite(SiteType $type): TestResponse
    {
        return $this->asOwner()->post(route('sites.store'), [
            'name' => 'Квиз-сайт',
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
