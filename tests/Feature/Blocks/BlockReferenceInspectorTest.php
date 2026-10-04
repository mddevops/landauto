<?php

namespace Tests\Feature\Blocks;

use App\Blocks\BlockReferenceInspector;
use App\Enums\WorkspaceRole;
use App\Models\BlockInstance;
use App\Models\Form;
use App\Models\Page;
use App\Models\Popup;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Database\Seeders\OfficialBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlockReferenceInspectorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Workspace $workspace;

    private Site $site;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OfficialBlockSeeder::class);
        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create();
        $this->workspace->addMember($this->user, WorkspaceRole::Owner);
        $this->site = Site::factory()->for($this->workspace)->create();
        $this->page = Page::factory()->for($this->site)->home()->create();
    }

    public function test_deleted_or_disabled_targets_are_reported_without_rewriting_state(): void
    {
        $offers = Page::factory()->for($this->site)->create(['slug' => 'offers', 'title' => 'Предложения']);
        $target = $this->block('cta');
        $popup = Popup::factory()->for($this->site)->create();
        $toPage = $this->block('cta', ['type' => 'open_page', 'page' => $offers->public_id]);
        $toBlock = $this->block('cta', ['type' => 'scroll_to', 'block' => $target->public_id]);
        $toPopup = $this->block('cta', ['type' => 'open_popup', 'popup' => $popup->public_id]);

        $this->assertSame([], $this->inspector()->inspectPage($this->page));

        $this->as()->delete(route('sites.pages.destroy', [$this->site, $offers]))->assertSessionHasNoErrors();
        $this->as()->delete(route('sites.blocks.destroy', [$this->site, $target]))->assertSessionHasNoErrors();
        $popup->update(['status' => false]);

        $issues = $this->inspector()->inspectPage($this->page);

        $this->assertSame([$toPage->public_id, $toBlock->public_id, $toPopup->public_id], array_keys($issues));
        $this->assertSame([
            'kind' => 'page',
            'path' => 'state.button.action.page',
            'target' => $offers->public_id,
            'severity' => 'error',
            'message' => 'Действие ведёт на страницу, которой больше нет.',
        ], $issues[$toPage->public_id][0]);
        $this->assertSame(['block', 'Действие прокручивает к блоку, которого больше нет на этой странице.'], [$issues[$toBlock->public_id][0]['kind'], $issues[$toBlock->public_id][0]['message']]);
        $this->assertSame(['popup', 'Действие открывает попап, который удалён или выключен.'], [$issues[$toPopup->public_id][0]['kind'], $issues[$toPopup->public_id][0]['message']]);

        $this->assertSame($offers->public_id, $toPage->fresh()?->state_json['button']['action']['page']);
        $this->assertSame($target->public_id, $toBlock->fresh()?->state_json['button']['action']['block']);

        $flat = $this->inspector()->inspectSite($this->site);
        $this->assertCount(3, $flat);
        $this->assertSame([$this->page->public_id, $toPage->public_id], [$flat[0]['page'], $flat[0]['block']]);
    }

    public function test_disabled_popup_form_is_a_warning_and_hidden_targets_count_only_for_publishing(): void
    {
        $form = Form::factory()->for($this->site)->withLeadFields()->create();
        $popup = Popup::factory()->for($this->site)->create();
        $popup->form()->associate($form)->save();
        $target = $this->block('cta');
        $toPopup = $this->block('cta', ['type' => 'open_popup', 'popup' => $popup->public_id]);
        $toBlock = $this->block('cta', ['type' => 'scroll_to', 'block' => $target->public_id]);

        $form->update(['status' => false]);
        $target->forceFill(['is_hidden' => true])->save();

        $draft = $this->inspector()->inspectPage($this->page);
        $this->assertSame([$toPopup->public_id], array_keys($draft));
        $this->assertSame(['popup_form', 'warning'], [$draft[$toPopup->public_id][0]['kind'], $draft[$toPopup->public_id][0]['severity']]);

        $published = $this->inspector()->inspectPage($this->page, null, true);
        $this->assertSame(['hidden_block', 'error'], [$published[$toBlock->public_id][0]['kind'], $published[$toBlock->public_id][0]['severity']]);

        $toBlock->forceFill(['is_hidden' => true])->save();
        $this->assertArrayNotHasKey($toBlock->public_id, $this->inspector()->inspectPage($this->page, null, true));
    }

    public function test_designer_receives_reference_issues_per_block(): void
    {
        $offers = Page::factory()->for($this->site)->create(['slug' => 'offers']);
        $stale = $this->block('cta', ['type' => 'open_page', 'page' => $offers->public_id]);
        $offers->delete();

        $this->as()->get(route('sites.designer', $this->site))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has("referenceIssues.{$stale->public_id}", 1)
                ->where("referenceIssues.{$stale->public_id}.0.path", 'state.button.action.page')
                ->where("referenceIssues.{$stale->public_id}.0.message", 'Действие ведёт на страницу, которой больше нет.'));
    }

    /**
     * @param  array<string, mixed>|null  $action
     */
    private function block(string $slug, ?array $action = null): BlockInstance
    {
        $this->as()->post(route('sites.blocks.store', [$this->site, $this->page]), ['block' => $slug])->assertSessionHasNoErrors();
        $block = $this->page->blocks()->reorder()->latest('id')->firstOrFail();

        if ($action !== null) {
            $this->as()->patch(route('sites.blocks.state', [$this->site, $block]), [
                'state' => ['button' => ['label' => 'Подробнее', 'action' => $action]],
            ])->assertSessionHasNoErrors();
        }

        return $block;
    }

    private function inspector(): BlockReferenceInspector
    {
        return app(BlockReferenceInspector::class);
    }

    private function as(): static
    {
        return $this->actingAs($this->user)->withSession([WorkspaceContext::SESSION_KEY => $this->workspace->public_id]);
    }
}
